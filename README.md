# Бронирование переговорных

Этот репозиторий создан на основе https://github.com/rcsi-science/demo-booking. Я добавил в него свои коммиты в соответствии с заданием.

Помимо изменений исходного кода, а также добавления REVIEW.md и AI_NOTES.md, изменено имя проекта в compose.yaml. А потому создадутся новые контейнеры. 

## Запуск

Поскольку это клон прежнего проекта, порядок запуска прежний. Повторяю его.

Нужны Docker и Docker Compose.

```sh
docker compose up -d --wait
docker compose exec app php artisan migrate:fresh --seed
```

Первый запуск занимает несколько минут: контейнер устанавливает зависимости. API доступно по адресу `http://localhost:8000/api`.

## Тесты

```sh
docker compose exec app php artisan test
```

Тесты идут на PostgreSQL в отдельной базе `testing`.

## API

Все методы требуют заголовки `Authorization: Bearer <токен>` и `Accept: application/json`. Без `Accept` запрос без токена вернёт 500 вместо 401.

| Метод | Адрес | Что делает |
| --- | --- | --- |
| GET | `/api/bookings` | Мои брони |
| POST | `/api/bookings` | Создать бронь |
| DELETE | `/api/bookings/{id}` | Отменить бронь |
| GET | `/api/rooms/{room}/bookings/today` | Брони комнаты на сегодня |

Пример:

```sh
curl -X POST http://localhost:8000/api/bookings \
  -H "Authorization: Bearer anna-token" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"room_id": 1, "user_id": 1, "title": "Планёрка", "starts_at": "2026-11-02T10:00:00+03:00", "ends_at": "2026-11-02T11:00:00+03:00"}'
```

Лог созданных броней пишется в `storage/logs/bookings.log`.
