# 勤怠管理アプリ

一般ユーザーの勤怠打刻・勤怠確認・修正申請と、管理者による勤怠管理・修正申請承認を行うアプリケーションです。

## 環境構築

### Dockerビルド

1. `git clone <リポジトリURL>`
2. `cd attendance-app`
3. 依存パッケージのインストール（Sail 起動前のため Docker 経由で実行）

    ```bash
    docker run --rm \
        -u "$(id -u):$(id -g)" \
        -v "$(pwd):/var/www/html" \
        -w /var/www/html \
        laravelsail/php82-composer:latest \
        composer install --ignore-platform-reqs
    ```

4. `cp .env.example .env`（DB・メールの環境変数を設定）
5. `./vendor/bin/sail up -d`

### Laravel環境構築

1. `./vendor/bin/sail artisan key:generate`
2. `./vendor/bin/sail artisan migrate --seed`
3. `./vendor/bin/sail npm install`
4. `./vendor/bin/sail npm run build`（開発時は `npm run dev`）

## 使用技術（実行環境）

- PHP 8.x
- Laravel 10.x
- Laravel Fortify（認証）
- MySQL 8.4
- Vite
- Docker（Laravel Sail）/ phpMyAdmin / Mailpit

## 開発状況

| 項目 | 状況 |
| --- | --- |
| 環境構築（Docker / Sail） | 完了 |
| Fortify 導入 | 完了（会員登録・ログインのビューを設定済み） |
| DB設計（マイグレーション） | 完了（users / attendances / break_times / stamp_requests / stamp_breaks） |
| モデル・リレーション | 完了 |
| Seeder（テストアカウント・勤怠ダミーデータ） | 完了 |
| 認証（PG01 会員登録・PG02 ログイン・PG07 管理者ログイン）とログアウト | 完了 |
| 勤怠登録（PG03）・勤怠一覧（PG04）・管理者の勤怠一覧（PG08） | 完了 |
| 勤怠詳細・修正申請・申請一覧・承認・スタッフ一覧（PG05, PG06, PG09〜PG13） | 未実装（ルートの定義とビュー・CSS のみ作成済み） |
| ★ PG14 マイ勤怠レポート（応用） | 対象外（現時点では実装しない） |

## ER図

![ER図](./er_diagram.drawio.png)

## テストアカウント

`php artisan migrate --seed` 実行後、以下のアカウントでログインできます。

| 種別 | 名前 | メールアドレス | パスワード |
| --- | --- | --- | --- |
| 管理者 | ユーザー３（管理者） | user3@example.com | password |
| 一般ユーザー | ユーザー１（一般） | user1@example.com | password |
| 一般ユーザー | ユーザー２（一般） | user2@example.com | password |

一般ユーザーには、直近60日間の平日分の勤怠・休憩ダミーデータ（今日の分を除く）が登録されます。

## 画面一覧

| 画面ID | 画面名称 | パス |
| --- | --- | --- |
| PG01 | 会員登録画面（一般ユーザー） | /register |
| PG02 | ログイン画面（一般ユーザー） | /login |
| PG03 | 勤怠登録画面（一般ユーザー） | /attendance |
| PG04 | 勤怠一覧画面（一般ユーザー） | /attendance/list |
| PG05 | 勤怠詳細画面（一般ユーザー） | /attendance/{id} |
| PG06 | 申請一覧画面（一般ユーザー） | /stamp_correction_request/list |
| PG07 | ログイン画面（管理者） | /admin/login |
| PG08 | 勤怠一覧画面（管理者） | /admin/attendance/list |
| PG09 | 勤怠詳細画面（管理者） | /attendance/{id}（一般ユーザーと同じパス。認証ミドルウェアで区別） |
| PG10 | スタッフ一覧画面（管理者） | /admin/staff/list |
| PG11 | スタッフ別勤怠一覧画面（管理者） | /admin/attendance/staff/{id} |
| PG12 | 申請一覧画面（管理者） | /stamp_correction_request/list（一般ユーザーと同じパス。認証ミドルウェアで区別） |
| PG13 | 修正申請承認画面（管理者） | /stamp_correction_request/approve/{attendance_correct_request_id} |
| ★ PG14 | マイ勤怠レポート画面（一般ユーザー） | /attendance/report |

## URL

- 開発環境：http://localhost/
- phpMyAdmin：http://localhost:8080/
- Mailpit：http://localhost:8025/
