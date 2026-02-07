# 薬袋印刷WEBシステム

## 起動方法
```bash
php -S 0.0.0.0:8000 -t public
```

- メイン画面: `http://localhost:8000/index.php`
- 医薬品管理: `http://localhost:8000/admin.php`
- 薬局情報設定: `http://localhost:8000/pharmacy.php`

初回アクセス時に SQLite DB (`database/app.sqlite`) とテーブルが自動作成されます。
