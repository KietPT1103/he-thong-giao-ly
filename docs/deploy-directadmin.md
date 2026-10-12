# Deploy frontend lên DirectAdmin

Workflow `.github/workflows/deploy-frontend.yml` build Vue/Vite và upload vào
`domains/giaoxucairang.com/public_html/build/`, tính từ thư mục gốc FTP.
Chỉ cập nhật frontend, chưa triển khai backend Laravel, Blade views, ảnh trong
`public/images`, Composer dependencies, migration hoặc cache trên server.
Frontend mới phải tương thích với backend đang chạy.

## Thiết lập

1. Trong Settings → Secrets and variables → Actions, thêm repository secrets
   `FTP_HOST`, `FTP_USERNAME`, `FTP_PASSWORD`. Không đưa mật khẩu vào code.
2. Workflow dùng explicit FTPS, mặc định port 21, hostname
   `melody.maychu.cloud` đã kiểm tra chứng chỉ TLS. Có thể đổi hostname bằng
   repository variable `FTP_TLS_HOST`, port bằng `FTP_PORT`.
   Secret `FTP_HOST` cũ không còn được dùng trong workflow này.
3. Xác nhận tài khoản FTP nhìn thấy đường dẫn `domains/giaoxucairang.com/`.
   Trước lần đầu, tải bản sao `public_html/build/` để có thể khôi phục.
4. Commit và push file workflow lên `main`. Trong Actions chọn
   **Deploy frontend to DirectAdmin** → **Run workflow** → branch `main`.
   Các lần push thay đổi JS/CSS hoặc cấu hình build lên main cũng tự deploy.

## Runner và phương án dự phòng

Workflow mặc định dùng `windows-latest` để thử nhóm runner khác khi Ubuntu
không kết nối được tới hosting. Đây là phương án thử kết nối khác, chưa chứng
minh Windows runner vượt qua được giới hạn mạng của hosting. Trong Run workflow
có thể chọn Windows hoặc Ubuntu; variable `DEPLOY_RUNNER` thay đổi runner mặc
định cho push. Cả hai vẫn yêu cầu FTPS và kiểm tra chứng chỉ.

Nếu GitHub-hosted runners đều timeout, có thể deploy từ máy Windows có kết nối:

```powershell
powershell -NoProfile -File scripts/deploy-frontend.ps1
```

Script kiểm tra TCP, chạy npm ci/type-check/build, hỏi credentials FTP rồi upload
assets và manifest cuối cùng. Credentials không được lưu vào file hay Git.
Để chỉ kiểm tra kết nối: thêm `-CheckOnly`. Deploy thủ công từ máy không tự chạy
khi push; cần thiết lập self-hosted runner riêng nếu muốn tự động từ máy/VPS.
Script Windows dùng curl, giữ lại assets cũ, không đảm bảo upload manifest nguyên tử.

Type check và build phải thành công trước upload. Workflow upload assets trước,
manifest sau, không xóa assets cũ, không cập nhật `.env`, `storage`, backend,
`index.php` hay `.htaccess`. Dọn assets cũ thủ công khi đã xác nhận các bản cũ
không còn cần thiết. Upload manifest qua FTP không bảo đảm chuyển bản nguyên tử.
Artifact build được giữ 30 ngày trên GitHub.

## Kiểm tra lần đầu

- Mở Actions, xem bước thất bại nếu có. Không bật debug chứa credentials.
- Lỗi certificate: hỏi hosting hostname FTPS khớp chứng chỉ và sửa `FTP_HOST`;
  không tắt kiểm tra chứng chỉ.
- Lỗi AUTH TLS: xác nhận hosting có hỗ trợ explicit FTPS.
- Lỗi login: kiểm tra credentials FTP, có thể khác credentials DirectAdmin.
- Bước `Check FTP port from GitHub runner` timeout: xác nhận host/port và
  yêu cầu hosting kiểm tra firewall đối với GitHub-hosted runners.
- TCP thành công nhưng `cd` timeout: cần hosting kiểm tra FTPS/TLS, log đăng nhập
  và kết nối passive; TCP thành công chưa xác nhận được đăng nhập FTPS.
- Lỗi `cd`: kiểm tra thư mục gốc khi đăng nhập FTP và sửa đường dẫn workflow.
- Thành công: mở website, refresh và kiểm tra giao diện, Network/Console.

## Khôi phục

Nếu upload dở trước manifest, website thường tiếp tục dùng assets cũ.
Nếu bản mới lỗi sau khi upload manifest, upload lại bản sao `build/` đã tải,
hoặc artifact của lần chạy thành công trước đó; assets trước, manifest sau.
Revert commit frontend trên main sẽ chạy lại workflow nếu thuộc paths đã khai báo.

## Deploy toàn bộ Laravel

Cần xác nhận PHP version/extensions, nội dung `public_html/index.php` và cách
chạy Artisan (SSH hoặc công cụ của hosting). Backend dự kiến nằm tại
`domains/giaoxucairang.com/laravel_app_20260911_cdx01/` nhưng cần kiểm tra.
Không ghi đè `.env`, không tạo lại APP_KEY, không xóa storage hoặc database.
Backup database trước các migration production. Chưa tạo environment GitHub
`production` trong workflow này; cần cấu hình quyền environment riêng nếu muốn
hiển thị lịch sử Deployments.
