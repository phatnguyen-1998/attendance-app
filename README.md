# 勤怠管理アプリ

一般ユーザーの勤怠打刻・勤怠確認・修正申請と、管理者による勤怠管理・修正申請承認を行うアプリケーションです。
応用機能として、メール認証、マイ勤怠レポート、CSV出力、公開API（Laravel Sanctum）を実装しています。

## 環境構築

Docker が必要です。Windows の場合は WSL2 上で実行してください。

### Dockerビルド

1. リポジトリを取得（応用版は `advanced` ブランチ）

    ```bash
    git clone -b advanced https://github.com/phatnguyen-1998/attendance-app.git
    cd attendance-app
    ```

2. 依存パッケージのインストール

    ```bash
    docker run --rm \
        -u "$(id -u):$(id -g)" \
        -v "$(pwd):/var/www/html" \
        -w /var/www/html \
        -e COMPOSER_CACHE_DIR=/tmp/composer_cache \
        laravelsail/php82-composer:latest \
        composer install --ignore-platform-reqs
    ```

3. `.env` を作成（DB・メールの設定は `.env.example` のまま使えます）

    ```bash
    cp .env.example .env
    ```

4. コンテナを起動

    ```bash
    ./vendor/bin/sail up -d
    ```

※ Apple Silicon の場合は、`compose.yaml` の `mysql` サービスに `platform: 'linux/amd64'` を追加してください。

### Laravel環境構築

MySQL の起動完了を待ってから実行してください。

```bash
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail npm install
./vendor/bin/sail npm run build   # 開発時は npm run dev
```

Sanctum の設定ファイルとマイグレーションはリポジトリに含まれているため、追加の手順は不要です。

## ログイン情報

| 種別         | 名前                 | メールアドレス    | パスワード | ログイン画面                 |
| ------------ | -------------------- | ----------------- | ---------- | ---------------------------- |
| 管理者       | ユーザー３（管理者） | user3@example.com | password   | http://localhost/admin/login |
| 一般ユーザー | ユーザー１（一般）   | user1@example.com | password   | http://localhost/login       |
| 一般ユーザー | ユーザー２（一般）   | user2@example.com | password   | http://localhost/login       |

すべてメール認証済みです。

### ダミーデータ

- ユーザー１：レポート確認用（過去5ヶ月は各月15日の通常勤務、当月は17日分で残業・遅刻・早退・長時間労働を含む）
- ユーザー２：直近60日間の平日分（今日を除く）

ユーザー１の `/attendance/report` の想定値：総労働時間 744時間、総残業時間 10時間、平均 8時間5分、遅刻 2回、早退 1回、長時間労働 1日

## メール認証の確認

1. http://localhost/register で会員登録
2. 誘導画面の「認証はこちらから」で Mailpit（http://localhost:8025）を開く
3. 届いたメールの認証リンクを開くと勤怠登録画面に遷移

## 使用技術（実行環境）

- PHP 8.2
- Laravel 10.x
- Laravel Fortify（認証）
- Laravel Sanctum（APIトークン認証）
- MySQL 8.4
- Vite
- Docker（Laravel Sail）/ phpMyAdmin / Mailpit
- PHPUnit / Laravel Pint

## ER図

![ER図](./er_diagram.drawio.png)

## URL

- 開発環境：http://localhost/
- phpMyAdmin：http://localhost:8080/
- Mailpit：http://localhost:8025/

## 画面一覧

| 画面ID | 画面名称                             | パス                                                              |
| ------ | ------------------------------------ | ----------------------------------------------------------------- |
| PG01   | 会員登録画面（一般ユーザー）         | /register                                                         |
| PG02   | ログイン画面（一般ユーザー）         | /login                                                            |
| PG03   | 勤怠登録画面（一般ユーザー）         | /attendance                                                       |
| PG04   | 勤怠一覧画面（一般ユーザー）         | /attendance/list                                                  |
| PG05   | 勤怠詳細画面（一般ユーザー）         | /attendance/{id}                                                  |
| PG06   | 申請一覧画面（一般ユーザー）         | /stamp_correction_request/list                                    |
| PG07   | ログイン画面（管理者）               | /admin/login                                                      |
| PG08   | 勤怠一覧画面（管理者）               | /admin/attendance/list                                            |
| PG09   | 勤怠詳細画面（管理者）               | /attendance/{id}                                                  |
| PG10   | スタッフ一覧画面（管理者）           | /admin/staff/list                                                 |
| PG11   | スタッフ別勤怠一覧画面（管理者）     | /admin/attendance/staff/{id}                                      |
| PG12   | 申請一覧画面（管理者）               | /stamp_correction_request/list                                    |
| PG13   | 修正申請承認画面（管理者）           | /stamp_correction_request/approve/{attendance_correct_request_id} |
| PG14   | マイ勤怠レポート画面（一般ユーザー） | /attendance/report                                                |

CSV出力は、スタッフ別勤怠一覧画面の「CSV出力」ボタンから実行します。

## テストの実行方法

```bash
./vendor/bin/sail artisan test
```

テストは `testing` データベースを使用します（Sail の MySQL コンテナ初回起動時に作成されます）。

## 公開API

ベースURL：`http://localhost/api/v1`

| メソッド    | パス                                   | 内容     | 認証                     |
| ----------- | -------------------------------------- | -------- | ------------------------ |
| GET         | /attendance-records                    | 勤怠一覧 | 不要                     |
| GET         | /attendance-records/{attendanceRecord} | 勤怠詳細 | 不要                     |
| POST        | /attendance-records                    | 勤怠登録 | 必要                     |
| PUT / PATCH | /attendance-records/{attendanceRecord} | 勤怠更新 | 必要（本人または管理者） |
| DELETE      | /attendance-records/{attendanceRecord} | 勤怠削除 | 必要（本人または管理者） |

一覧は `user_id`、`date`（YYYY-MM-DD）、`month`（YYYY-MM）、`page`、`per_page`（最大100）を指定できます。

### トークンの発行

```bash
./vendor/bin/sail artisan tinker
```

```php
App\Models\User::where('email', 'user1@example.com')->first()->createToken('api')->plainTextToken;
```

### リクエスト例

```bash
curl -X POST http://localhost/api/v1/attendance-records \
    -H "Accept: application/json" \
    -H "Content-Type: application/json" \
    -H "Authorization: Bearer <発行したトークン>" \
    -d '{"date":"2030-01-07","clock_in":"09:00:00","clock_out":"18:00:00","comment":"APIから登録"}'
```

`Accept: application/json` を付けてください。主なステータス：200 / 201 / 204 / 401 / 403 / 404 / 422
