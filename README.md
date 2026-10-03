# Практикум №7 — Профілювання та оптимізація

Той самий домен (`products`), що й у практикумах №4–6. Ендпоінт для вимірювання:
`GET /api.php?resource=products`.

- `?mode=naive` — свідомо "повільна" версія: N+1-запит для суми `price*stock`.
- без `mode` (або `mode=optimized`) — один агрегатний запит `SUM(price*stock)` + файловий кеш (TTL 60с).

Запущено на: `php -S localhost:8000`, база `practicum4` (MySQL/MariaDB).

---

## Крок 1–2. Базова лінія "до" (режим `naive`)

### Профілювання (з поля `meta` у відповіді)

```bash
curl -s "http://localhost:8000/api.php?resource=products&mode=naive" | python3 -m json.tool
```

Подивись на блок `"meta"` у відповіді — там одразу `query_count`, `elapsed_ms`, `memory_mb`.

| Показник | Значення |
|---|---|
| Час виконання (мс) | _заповнити з meta.elapsed_ms_ |
| Пікова пам'ять (МБ) | _заповнити з meta.memory_mb_ |
| Кількість SQL-запитів | _заповнити з meta.query_count_ (має бути 1 + N, де N — кількість товарів) |

### Навантажувальне тестування

```bash
ab -n 200 -c 20 "http://localhost:8000/api.php?resource=products&mode=naive"
```

| Показник | Значення |
|---|---|
| Requests per second | _заповнити з виводу ab_ |
| Time per request (mean), мс | _заповнити_ |
| Failed requests | _заповнити (має бути 0)_ |

---

## Крок 3. Підтвердження проблеми N+1

`meta.query_count` у режимі `naive` дорівнює **1 + N**, де N — кількість товарів у каталозі
(1 запит на список `SELECT * FROM products`, і ще по одному запиту `SELECT price*stock ... WHERE id=:id`
на кожен товар у циклі). Це і є проблема N+1: кількість запитів росте лінійно з розміром каталогу.

---

## Крок 4. Індекс на стовпці `sku`

Фільтр `findBySku()` використовує `WHERE sku = :sku`. Перевірка через `EXPLAIN`:

```sql
EXPLAIN SELECT * FROM products WHERE sku = 'MAT-003';
```

**Важливо:** стовпець `sku` у схемі (`schema.sql`, практикум №4) оголошений як `UNIQUE`,
а `UNIQUE` в MySQL автоматично створює індекс. Тобто `EXPLAIN` покаже `type=ref`
(а не `ALL`) **ще до** явного `CREATE INDEX` — фільтр за SKU вже оптимізований з практикуму №4.

Явний індекс (для відповідності кроку методички) усе одно доданий у `schema.sql`:
```sql
CREATE INDEX idx_products_sku ON products (sku);
```
MySQL або додасть технічно надлишковий другий індекс (не завадить, лише трохи
сповільнить INSERT/UPDATE), або — залежно від версії — попередить, що такий
індекс уже покритий `UNIQUE`-обмеженням.

| | type | rows |
|---|---|---|
| До (з UNIQUE, без явного CREATE INDEX) | _заповнити (очікується: ref)_ | _заповнити_ |
| Після (з явним CREATE INDEX) | _заповнити (очікується: той самий ref)_ | _заповнити_ |

---

## Крок 5. Усунення N+1

```bash
curl -s "http://localhost:8000/api.php?resource=products" | python3 -m json.tool
```

`meta.query_count` тепер дорівнює **2** при першому запиті (1 — список товарів,
1 — агрегатний `SELECT SUM(price*stock)`), замість 1+N. Замінено цикл з окремим
запитом на кожен товар (`getTotalStockValueNaive()`) одним агрегатним запитом
(`totalStockValue()`), що виконує підсумовування безпосередньо на стороні СУБД.

---

## Крок 6. Кешування

Повтори той самий запит ще раз протягом 60 секунд:

```bash
curl -s "http://localhost:8000/api.php?resource=products" | python3 -m json.tool
```

Другий (і кожен наступний у межах TTL) виклик має показати `"meta.cache": "hit"`
і `meta.query_count = 1` (лише список товарів — сам агрегат узятий з файлового
кешу без повторного запиту до БД). Через 60 секунд кеш протухає природно
(`cache: "miss"` знову, і файл кешу перезаписується).

Кеш лежить у `sys_get_temp_dir()` з назвою `practicum07_cache_<hash>.json`.
Якщо дані зміняться (наприклад, при `INSERT`/`UPDATE` з практикуму №4 чи №6),
правильна практика — викликати `invalidateCache('total_stock_value')`
(є в `lib/cache.php`) одразу після запису, щоб кеш не показував застарілу суму
до завершення TTL.

---

## Крок 7. Повторний замір "після" (режим `optimized`)

```bash
curl -s "http://localhost:8000/api.php?resource=products" | python3 -m json.tool
ab -n 200 -c 20 "http://localhost:8000/api.php?resource=products"
```

| Показник | До (naive) | Після (optimized, cache hit) |
|---|---|---|
| Час виконання (мс) | _заповнити_ | _заповнити_ |
| Пікова пам'ять (МБ) | _заповнити_ | _заповнити_ |
| Кількість SQL-запитів | 1 + N | 1 (при cache hit) |
| Requests per second (ab) | _заповнити_ | _заповнити_ |
| Time per request, mean (ab) | _заповнити_ | _заповнити_ |
| Failed requests (ab) | _заповнити_ | _заповнити_ |

---

## Крок 8. Висновок

_Одне речення:_ час виконання й кількість SQL-запитів падають, бо замість N окремих
запитів по одному товару виконується єдиний агрегатний запит `SUM(price*stock)`
на стороні MySQL, а повторні запити протягом TTL взагалі не звертаються до БД
завдяки файловому кешу.
