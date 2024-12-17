<?php
session_start();
include('db.php');

// Kiểm tra xem người dùng đã đăng nhập chưa
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

// Kiểm tra xem giỏ hàng có trống không
if (empty($_SESSION['cart'])) {
    header('Location: cart.php');
    exit;
}

$userId = $_SESSION['user']['id']; // Lấy ID người dùng từ session
$cartItems = $_SESSION['cart']; // Lấy giỏ hàng từ session

// Kiểm tra dữ liệu thanh toán từ form
$paymentMethod = isset($_POST['payment_method']) ? $_POST['payment_method'] : 'cash';
$deliveryAddress = isset($_POST['address']) ? $_POST['address'] : '123 Đường ABC, Thành phố XYZ'; // Có thể lấy từ form

// Bắt đầu transaction
$conn->beginTransaction();

try {
    // Tính tổng tiền từ giỏ hàng
    $totalAmount = 0;
    foreach ($cartItems as $item) {
        $totalAmount += $item['quantity'] * getProductPrice($item['productId'], $conn);
    }

    // Thêm đơn hàng vào bảng `donhang`
    $sqlInsertDonHang = "INSERT INTO donhang (mand, ngaydat, tongtien, diachigiaohang, matrangthai_giaohang, matrangthai_thanhtoan, phuongthucthanhtoan)
                         VALUES (:mand, GETDATE(), :tongtien, :diachigiaohang, 1, 1, :phuongthucthanhtoan)";
    $stmtDonHang = $conn->prepare($sqlInsertDonHang);
    $stmtDonHang->execute([
        ':mand' => $userId,
        ':tongtien' => $totalAmount,
        ':diachigiaohang' => $deliveryAddress,
        ':phuongthucthanhtoan' => $paymentMethod
    ]);

    // Lấy ID đơn hàng vừa thêm
    $donHangId = $conn->lastInsertId();

    // Thêm chi tiết đơn hàng vào bảng `chitietdonhang`
    $sqlInsertChiTiet = "INSERT INTO chitietdonhang (madh, madonhang, soluong, dongia, size)
                         VALUES (:madh, :madonhang, :soluong, :dongia, :size)";
    $stmtChiTiet = $conn->prepare($sqlInsertChiTiet);

    foreach ($cartItems as $item) {
        $productPrice = getProductPrice($item['productId'], $conn);
        $stmtChiTiet->execute([
            ':madh' => $item['productId'],
            ':madonhang' => $donHangId,
            ':soluong' => $item['quantity'],
            ':dongia' => $productPrice,
            ':size' => $item['size']
        ]);
    }

    // Hoàn thành transaction
    $conn->commit();

    // Xóa giỏ hàng
    unset($_SESSION['cart']);

    // Chuyển hướng đến trang thông báo thành công
    header('Location: success.php');
    exit;
} catch (Exception $e) {
    // Rollback nếu có lỗi
    $conn->rollBack();
    die("Lỗi khi đặt hàng: " . $e->getMessage());
}

// Hàm lấy giá sản phẩm từ cơ sở dữ liệu
function getProductPrice($productId, $conn)
{
    $sql = "SELECT gia FROM dongho WHERE madh = :productId";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':productId' => $productId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['gia'] : 0;
}
