<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include('db.php');

// Xử lý cập nhật số lượng hoặc xóa sản phẩm nếu có request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $productId = isset($_POST['productId']) ? $_POST['productId'] : null;

        if ($_POST['action'] === 'update' && isset($_POST['quantity'])) {
            $quantity = max(1, min(10, (int)$_POST['quantity']));
            $_SESSION['cart'][$productId]['quantity'] = $quantity;
            if (isset($_POST['size'])) {
                $_SESSION['cart'][$productId]['size'] = $_POST['size'];
            }
        } else if ($_POST['action'] === 'remove') {
            unset($_SESSION['cart'][$productId]);
        }

        // Redirect để tránh gửi lại form khi refresh
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Lấy dữ liệu sản phẩm từ giỏ hàng
$cartItems = isset($_SESSION['cart']) ? $_SESSION['cart'] : [];

// Lấy thông tin chi tiết sản phẩm từ cơ sở dữ liệu
$products = [];
if (!empty($cartItems)) {
    $productIds = implode(',', array_keys($cartItems));
    $sql = "SELECT madh, tendongho, hinhanh, gia FROM dongho WHERE madh IN ($productIds)";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Tính tổng tiền
$totalAmount = 0;
foreach ($products as $product) {
    $totalAmount += $product['gia'] * $cartItems[$product['madh']]['quantity'];
}

// Nội dung cho layout
ob_start();
?>

<div class="cart-section">
    <h2 class="section-title">Giỏ Hàng Của Bạn</h2>
    <?php if (!empty($products)): ?>
        <div class="cart-table">
            <table>
                <thead>
                    <tr>
                        <th>Sản Phẩm</th>
                        <th>Giá</th>
                        <th>Size</th>
                        <th>Số Lượng</th>
                        <th>Thành Tiền</th>
                        <th>Thao Tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                        <tr class="cart-item" data-product-id="<?php echo $product['madh']; ?>">
                            <td class="product-info">
                                <img src="images/<?php echo htmlspecialchars($product['hinhanh']); ?>"
                                    alt="<?php echo htmlspecialchars($product['tendongho']); ?>"
                                    class="product-image">
                                <div class="product-details">
                                    <h3><?php echo htmlspecialchars($product['tendongho']); ?></h3>
                                </div>
                            </td>
                            <td class="product-price">
                                <?php echo number_format($product['gia'], 0, ',', '.'); ?> VNĐ
                            </td>
                            <td class="product-size">
                                <form method="POST" class="size-form">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="productId" value="<?php echo $product['madh']; ?>">
                                    <input type="hidden" name="quantity" value="<?php echo $cartItems[$product['madh']]['quantity']; ?>">
                                    <select name="size" class="size-select" onchange="this.form.submit()">
                                        <?php
                                        $sizes = range(37, 41);
                                        foreach ($sizes as $size) {
                                            $selected = (isset($cartItems[$product['madh']]['size']) && $cartItems[$product['madh']]['size'] == $size) ? 'selected' : '';
                                            echo "<option value='$size' $selected>$size</option>";
                                        }
                                        ?>
                                    </select>
                                </form>
                            </td>
                            <td class="product-quantity">
                                <form method="POST" class="quantity-form">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="productId" value="<?php echo $product['madh']; ?>">
                                    <div class="quantity-controls">
                                        <button type="button" class="quantity-btn minus" onclick="updateQuantity(this, 'decrease')">-</button>
                                        <input type="number" name="quantity" value="<?php echo $cartItems[$product['madh']]['quantity']; ?>"
                                            min="1" max="10" class="quantity-input"
                                            onchange="updateQuantity(this, 'input')">
                                        <button type="button" class="quantity-btn plus" onclick="updateQuantity(this, 'increase')">+</button>
                                    </div>
                                </form>
                            </td>
                            <td class="product-subtotal">
                                <?php echo number_format($product['gia'] * $cartItems[$product['madh']]['quantity'], 0, ',', '.'); ?> VNĐ
                            </td>
                            <td class="product-remove">
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="remove">
                                    <input type="hidden" name="productId" value="<?php echo $product['madh']; ?>">
                                    <button type="submit" class="remove-btn" onclick="return confirm('Bạn có chắc muốn xóa sản phẩm này khỏi giỏ hàng?')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="cart-summary">
            <div class="summary-row">
                <span>Tổng tiền:</span>
                <span class="total-amount"><?php echo number_format($totalAmount, 0, ',', '.'); ?> VNĐ</span>
            </div>
            <form action="checkout.php" method="POST" class="checkout-form">
                <!-- Payment Method Dropdown -->
                <div class="form-group">
                    <label for="payment-method" class="form-label">Phương thức thanh toán:</label>
                    <select name="payment_method" id="payment-method" class="form-control stylish-select">
                        <option value="cash">Tiền Mặt</option>
                        <option value="atm">Thẻ ATM</option>
                    </select>
                </div>

                <!-- Address Input -->
                <div class="form-group">
                    <label for="address" class="form-label">Địa chỉ:</label>
                    <input type="text" name="address" id="address" class="form-control stylish-input" placeholder="Nhập địa chỉ của bạn" required>
                </div>

                <button type="submit" class="checkout-btn stylish-button">Tiến hành thanh toán</button>
            </form>

            <style>
                .checkout-form {
                    background-color: #f9f9f9;
                    padding: 20px;
                    border-radius: 8px;
                    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
                }

                .form-label {
                    font-weight: bold;
                    margin-bottom: 5px;
                    display: block;
                }

                .stylish-select,
                .stylish-input {
                    width: 100%;
                    padding: 10px;
                    margin-bottom: 15px;
                    border: 1px solid #ddd;
                    border-radius: 4px;
                    transition: border-color 0.3s;
                }

                .stylish-select:focus,
                .stylish-input:focus {
                    border-color: #007bff;
                    outline: none;
                }

                .stylish-button {
                    background-color: #007bff;
                    color: #fff;
                    padding: 10px 20px;
                    border: none;
                    border-radius: 4px;
                    cursor: pointer;
                    transition: background-color 0.3s;
                }

                .stylish-button:hover {
                    background-color: #0056b3;
                }
            </style>
        </div>
    <?php else: ?>
        <div class="empty-cart">
            <i class="fas fa-shopping-cart"></i>
            <p>Giỏ hàng của bạn đang trống</p>
            <a href="http://localhost:8080/quanlydongho/Client/products.php" class="continue-shopping">Tiếp tục mua sắm</a>
        </div>
    <?php endif; ?>
</div>

   <!-- Modal Payment Success -->
    <div class="modal fade" id="transactionSuccessModal" tabindex="-1" aria-labelledby="transactionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="transactionModalLabel">🎉 Thanh Toán Thành Công!</h5>
                    <button type="button" class="btn-close text-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <p class="fs-5">Cảm ơn quý khách đã mua hàng!</p>
                    <p class="text-muted">Hy vọng quý khách sẽ hài lòng với sản phẩm/dịch vụ của chúng tôi.</p>
                    <div class="mt-3">
                        <img src="https://via.placeholder.com/150" alt="Success" class="img-fluid rounded-circle shadow-sm">
                    </div>
                </div>
                <div class="modal-footer d-flex justify-content-center">
                    <button type="button" class="btn btn-success btn-lg px-4" id="redirectHome">OK</button>
                </div>
            </div>
        </div>
    </div>

<style>
    .cart-section {
        padding: 2rem;
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        margin: 2rem auto;
    }

    .section-title {
        font-family: 'Playfair Display', serif;
        color: #333;
        margin-bottom: 2rem;
    }

    .cart-table {
        width: 100%;
        overflow-x: auto;
    }

    .cart-table table {
        width: 100%;
        border-collapse: collapse;
    }

    .cart-table th {
        background: #f8f9fa;
        padding: 1rem;
        text-align: left;
        font-weight: 500;
    }

    .cart-table td {
        padding: 1rem;
        border-bottom: 1px solid #eee;
        vertical-align: middle;
    }

    .product-info {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .product-image {
        width: 80px;
        height: 80px;
        object-fit: cover;
        border-radius: 4px;
    }

    .quantity-controls {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .quantity-btn {
        width: 30px;
        height: 30px;
        border: 1px solid #ddd;
        background: #fff;
        border-radius: 4px;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .quantity-btn:hover {
        background: #f8f9fa;
    }

    .quantity-input {
        width: 50px;
        text-align: center;
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 0.3rem;
    }

    .remove-btn {
        background: none;
        border: none;
        color: #dc3545;
        cursor: pointer;
        transition: color 0.3s ease;
    }

    .remove-btn:hover {
        color: #c82333;
    }

    .cart-summary {
        margin-top: 2rem;
        padding: 1rem;
        background: #f8f9fa;
        border-radius: 8px;
    }

    .summary-row {
        display: flex;
        justify-content: space-between;
        padding: 1rem;
        font-size: 1.2rem;
        font-weight: 500;
    }

    .checkout-btn {
        width: 100%;
        padding: 1rem;
        background: #d4af37;
        color: #fff;
        border: none;
        border-radius: 4px;
        font-size: 1.1rem;
        cursor: pointer;
        transition: background 0.3s ease;
    }

    .checkout-btn:hover {
        background: #c4a030;
    }

    .empty-cart {
        text-align: center;
        padding: 3rem;
    }

    .empty-cart i {
        font-size: 4rem;
        color: #ccc;
        margin-bottom: 1rem;
    }

    .continue-shopping {
        display: inline-block;
        margin-top: 1rem;
        padding: 0.8rem 1.5rem;
        background: #d4af37;
        color: #fff;
        text-decoration: none;
        border-radius: 4px;
        transition: background 0.3s ease;
    }

    .continue-shopping:hover {
        background: #c4a030;
    }

    .size-select {
        padding: 5px;
        border: 1px solid #ddd;
        border-radius: 4px;
        width: 70px;
    }
</style>

<script>
    function updateQuantity(element, action) {
        const form = element.closest('.quantity-form');
        const quantityInput = form.querySelector('.quantity-input');
        let currentQuantity = parseInt(quantityInput.value);

        if (action === 'increase') {
            currentQuantity = Math.min(currentQuantity + 1, 10);
        } else if (action === 'decrease') {
            currentQuantity = Math.max(currentQuantity - 1, 1);
        } else if (action === 'input') {
            currentQuantity = Math.max(1, Math.min(parseInt(quantityInput.value), 10));
        }

        quantityInput.value = currentQuantity;
        form.submit();
    }


    document.addEventListener('DOMContentLoaded', function () {
        if (window.location.href.includes("vnp_Amount")) {
         
            let transactionModal = new bootstrap.Modal(document.getElementById('transactionSuccessModal'));
            transactionModal.show();

            document.getElementById('redirectHome').addEventListener('click', function () {
                // Thay home url cua em o day
                window.location.href = "http://quanlydongho:8001/Client/products.php";
            });
        }
    });


</script>

<?php
$content = ob_get_clean();
include 'layout.php';
?>