# 🗂️ todo_ei_app  
アイゼンハワーマトリクス（重要度×緊急度）の考え方を、  
Webブラウザ上で試せるようにした学習用 PHP アプリです。

---

## 📘 Overview  
このアプリは、PHP + HTML の基本を理解しながら  
「フォーム入力 → データ取得 → 画面表示」の流れを学ぶために作成しました。

- PHP の基礎構文  
- フォームからの POST 受け取り  
- 条件判定（if 文）  
- HTML テンプレートの分割  
- 画面遷移の流れ整理  

を意識して実装しています。

> **今後の複雑な実装へ発展させるための基礎固めとして作っています。**

---

## 🧰 Tech Stack（使用技術）

| 分類 | 内容 |
|------|------|
| Language | PHP 8.2 / HTML5 |
| Local Env | MAMP / macOS |
| Tools | VSCode / Git / GitHub |

---

## 🗃️ Features（機能一覧）
- タスクの登録（フォーム入力）
- アイゼンハワーマトリクス（重要×緊急）の4象限分類
- 結果画面での分類表示

---

## 📂 Directory Structure（フォルダ構成）

todo_ei_app/

├── index.php # トップ画面（入力フォーム）

├── create_task.php # 入力値を受け取り分類・表示

├── eisenhower_color_palette.html # カラーパターン1

├── eisenhower_color_palette2.html # カラーパターン2

├── eisenhower_color_palette3.html # カラーパターン3

├── eisenhower_color_palette4.html # カラーパターン4

└── eisenhower_color_palette_head.html # デザイン共通化用


> **初学者ポイント：**  
> HTML を細かく分割することで「共通化」「再利用」の感覚をつかむ練習になっています。

---

## 🔄 Flow（処理の流れ）
1. `index.php`：フォーム入力  
2. `create_task.php`：POSTで受け取り  
3. 重要・緊急の2つの値から象限を判定  
4. HTML を読んで画面に表示  
5. 別のカラーパレットを表示できる

> **この構造は、今後作る「オセロ」「会員管理」「業務システム」の  
> フロント → バック → 表示 の流れと基本的に同じです。**

---

## 🧠 What I Learned
- PHP の基本（変数、if文、POST受け取り）
- HTML の共通テンプレート化
- 画面遷移の設計（1ページ→処理→表示）
- Git / GitHub でのバージョン管理

> 技術者向けの説明に少しずつ慣れるため、  
> 重要な処理はコード内にもコメントを残しています。

---

## 🎯 Next Steps（今後の発展方針）
- フォーム入力のバリデーション（未入力チェックなど）
- DB（MySQL）と連携して永続化
- 「編集・削除」を含むCRUD化
- カラーパレットを PHP 化して共通化
- オセロアプリの CRUD 設計につなげる
- 会員管理やログイン機能（Laravel Breeze）へ拡張

---

## 📝 Notes
このリポジトリは学習用のため、  
今後も改善・リファクタリングを継続していきます。



