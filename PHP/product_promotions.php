<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html"); 
    exit();
}

require_once 'config.php';

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];

$checkTableQuery = "SHOW TABLES LIKE 'product_promotions'";
$result = $conn->query($checkTableQuery);
if ($result->num_rows == 0) {
    $createTableQuery = "CREATE TABLE product_promotions (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        product_name VARCHAR(255) NOT NULL,
        discount VARCHAR(50) NOT NULL,
        store VARCHAR(255) NOT NULL,
        description TEXT,
        added_by INT(11),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (added_by) REFERENCES users(id)
    )";
    $conn->query($createTableQuery);
}

$promotionsQuery = "SELECT * FROM product_promotions ORDER BY created_at DESC";
$promotions = $conn->query($promotionsQuery);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Промоции на продукти - DigiSpesti</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="modal.css">
    <style>
        .promotions-container {
            margin-top: 24px;
        }
        
        .promotions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 24px;
            margin-top: 24px;
        }
        
        .promotion-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            padding: 20px;
            transition: transform 0.3s ease;
            animation: fadeIn 1s ease-in-out;
            position: relative;
        }
        
        .promotion-card:hover {
            transform: translateY(-5px);
        }
        
        .promotion-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }
        
        .promotion-card-title {
            font-size: 18px;
            font-weight: bold;
            margin: 0;
        }
        
        .promotion-card-discount {
            font-size: 24px;
            font-weight: bold;
            margin: 8px 0;
            color: #d4af37;
        }
        
        .promotion-card-store {
            color: #666;
            margin-bottom: 16px;
            font-size: 16px;
            font-weight: 500;
        }
        
        .promotion-card-description {
            color: #666;
            margin-bottom: 16px;
            font-size: 14px;
        }
        
        .promotion-card-date {
            font-size: 12px;
            color: #999;
            text-align: right;
            margin-top: 10px;
        }
        
        .no-promotions {
            text-align: center;
            padding: 50px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .no-promotions h3 {
            margin-bottom: 20px;
            color: #666;
        }
        
        .key-modal-content {
            max-width: 400px;
        }
        
        .promotion-form-content {
            max-width: 500px;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }
        
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 8px;
        }
        
        .badge-discount {
            background-color: #ffc300;
            color: #1a1a1a;
        }
        
        .promotion-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 10px;
        }
        
        .delete-btn {
            background-color: #f44336;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 6px 12px;
            font-size: 14px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .delete-btn:hover {
            background-color: #d32f2f;
        }
        
        .confirm-modal-content {
            max-width: 400px;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <div class="logo-container">
                <a href="./index.php"><img src="image.png" alt="DigiSpesti Logo"></a>
            </div>
            <nav class="nav">
                <a href="index.php" class="nav-link">Начална страница</a>
                <a href="history.php" class="nav-link">Плащания</a>
                <a href="savings.php" class="nav-link">Спестявания</a>
                <a href="plan_budget.php" class="nav-link">Бюджет</a>
                <a href="product_promotions.php" class="nav-link active">Промоции</a>
                <form action="logout.php" method="POST" style="display: inline; margin-left: 20px;">
                    <button type="submit" class="btn btn-outline">Излезте</button>
                </form>
            </nav>
        </div>
    </header>

    <main class="container dashboard">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?php 
                    echo $_SESSION['success']; 
                    unset($_SESSION['success']);
                ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <?php 
                    echo $_SESSION['error']; 
                    unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>
        
        <div class="dashboard-header">
            <div>
                <h1>Промоции на продукти</h1>
                <p>Намери най-добрите оферти и намаления на продуктите</p>
            </div>
            <button class="btn btn-primary" id="addPromotionBtn">Добави промоция</button>
        </div>

        <div class="promotions-container">
            <?php if ($promotions->num_rows > 0): ?>
                <div class="promotions-grid">
                    <?php while ($promotion = $promotions->fetch_assoc()): ?>
                        <div class="promotion-card">
                            <div class="promotion-card-header">
                                <h3 class="promotion-card-title">
                                    <?php echo htmlspecialchars($promotion['product_name']); ?>
                                </h3>
                                <span class="badge badge-discount"><?php echo htmlspecialchars($promotion['discount']); ?></span>
                            </div>
                            <div class="promotion-card-store">
                                <?php echo htmlspecialchars($promotion['store']); ?>
                            </div>
                            <?php if (!empty($promotion['description'])): ?>
                                <div class="promotion-card-description">
                                    <?php echo htmlspecialchars($promotion['description']); ?>
                                </div>
                            <?php endif; ?>
                            <div class="promotion-card-date">
                                Added on <?php echo date('d F Y', strtotime($promotion['created_at'])); ?>
                            </div>
                            <div class="promotion-actions">
                                <button class="delete-btn" data-id="<?php echo $promotion['id']; ?>" data-product="<?php echo htmlspecialchars($promotion['product_name']); ?>">
                                    Изтрийте
                                </button>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="no-promotions">
                    <h3>Все още няма добавени промоции</h3>
                    <p>Бъди първият, добавил промоция на продукт!</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <div id="keyModal" class="modal">
        <div class="modal-content key-modal-content">
            <div class="modal-header">
                <h2>Потвърждение от продавача</h2>
                <span class="close-modal" id="closeKeyModal">&times;</span>
            </div>
            <div class="modal-body">
                <p>Моля напишете код на продавача, за да добавите промоция: </p>
                <div class="form-group">
                    <input type="password" id="sellerKey" class="form-input" placeholder="Код на продавача">
                </div>
                <div class="form-group" style="margin-top: 20px;">
                    <button type="button" class="btn btn-primary" id="verifyKeyBtn" style="width: 100%;">Потвърдете</button>
                </div>
            </div>
        </div>
    </div>

    <div id="deleteKeyModal" class="modal">
        <div class="modal-content key-modal-content">
            <div class="modal-header">
                <h2>Потвърждение на продавача</h2>
                <span class="close-modal" id="closeDeleteKeyModal">&times;</span>
            </div>
            <div class="modal-body">
                <p>Моля напишете код на продавача, за да изтриете промоция:</p>
                <div class="form-group">
                    <input type="password" id="deleteSellerKey" class="form-input" placeholder="Код на продавача">
                </div>
                <div class="form-group" style="margin-top: 20px;">
                    <button type="button" class="btn btn-primary" id="verifyDeleteKeyBtn" style="width: 100%;">Потвърдете</button>
                </div>
            </div>
        </div>
    </div>

    <div id="promotionModal" class="modal">
        <div class="modal-content promotion-form-content">
            <div class="modal-header">
                <h2>Добавете промоция на продукт</h2>
                <span class="close-modal" id="closePromotionModal">&times;</span>
            </div>
            <div class="modal-body">
                <form id="promotionForm" action="process_promotion.php" method="POST">
                    <div class="form-group">
                        <label for="product_name">Име на продукта: <span class="required">*</span></label>
                        <input type="text" id="product_name" name="product_name" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="discount">Намаление: <span class="required">*</span></label>
                        <input type="text" id="discount" name="discount" class="form-input" placeholder="Например 15% оферта" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="store">Магазин: <span class="required">*</span></label>
                        <input type="text" id="store" name="store" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Описание:</label>
                        <textarea id="description" name="description" class="form-input" rows="3" placeholder="Допълнителна информация"></textarea>
                    </div>
                    
                    <div class="form-group" style="margin-top: 20px;">
                        <button type="submit" class="btn btn-primary" style="width: 100%;">Запази промоция</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="confirmDeleteModal" class="modal">
        <div class="modal-content confirm-modal-content">
            <div class="modal-header">
                <h2>Потвърдете изтриване</h2>
                <span class="close-modal" id="closeConfirmDeleteModal">&times;</span>
            </div>
            <div class="modal-body">
                <p>Сигурни ли сте, че искате да изтриете тази промоция за <strong id="deleteProductName"></strong>?</p>
                <p>Това дайствие не може да се възстанови.</p>
                
                <form id="deleteForm" action="process_delete_promotion.php" method="POST">
                    <input type="hidden" id="deletePromotionId" name="promotion_id">
                    
                    <div class="form-group" style="margin-top: 20px; display: flex; justify-content: space-between;">
                        <button type="button" class="btn btn-outline" id="cancelDeleteBtn">Откажете</button>
                        <button type="submit" class="btn btn-primary" style="background-color: #f44336;">Изтрийте</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <footer class="container">
        <p>&copy; 2025 DigiSpesti. All rights reserved.</p>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const keyModal = document.getElementById('keyModal');
            const addPromotionBtn = document.getElementById('addPromotionBtn');
            const closeKeyModalBtn = document.getElementById('closeKeyModal');
            const verifyKeyBtn = document.getElementById('verifyKeyBtn');
            const sellerKeyInput = document.getElementById('sellerKey');
            
            const deleteKeyModal = document.getElementById('deleteKeyModal');
            const closeDeleteKeyModalBtn = document.getElementById('closeDeleteKeyModal');
            const verifyDeleteKeyBtn = document.getElementById('verifyDeleteKeyBtn');
            const deleteSellerKeyInput = document.getElementById('deleteSellerKey');
            
            const promotionModal = document.getElementById('promotionModal');
            const closePromotionModalBtn = document.getElementById('closePromotionModal');
            
            const confirmDeleteModal = document.getElementById('confirmDeleteModal');
            const closeConfirmDeleteModalBtn = document.getElementById('closeConfirmDeleteModal');
            const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');
            const deleteProductNameSpan = document.getElementById('deleteProductName');
            const deletePromotionIdInput = document.getElementById('deletePromotionId');
            
            const deleteButtons = document.querySelectorAll('.delete-btn');
            
            let currentPromotionId = null;
            let currentProductName = null;
            
            addPromotionBtn.addEventListener('click', function() {
                keyModal.style.display = 'flex';
                setTimeout(() => {
                    sellerKeyInput.focus();
                }, 100);
            });
            
            closeKeyModalBtn.addEventListener('click', function() {
                keyModal.style.display = 'none';
                sellerKeyInput.value = '';
            });
            
            closeDeleteKeyModalBtn.addEventListener('click', function() {
                deleteKeyModal.style.display = 'none';
                deleteSellerKeyInput.value = '';
            });
            
            closePromotionModalBtn.addEventListener('click', function() {
                promotionModal.style.display = 'none';
            });
            
            closeConfirmDeleteModalBtn.addEventListener('click', function() {
                confirmDeleteModal.style.display = 'none';
            });
            
            cancelDeleteBtn.addEventListener('click', function() {
                confirmDeleteModal.style.display = 'none';
            });
            
            verifyKeyBtn.addEventListener('click', function() {
                const key = sellerKeyInput.value.trim();
                
                if (key === 'seller123') {
                    keyModal.style.display = 'none';
                    promotionModal.style.display = 'flex';
                    sellerKeyInput.value = '';
                } else {
                    alert('Невалиден код на продавача. Моля опитайте отново.');
                }
            });
            
            verifyDeleteKeyBtn.addEventListener('click', function() {
                const key = deleteSellerKeyInput.value.trim();
                
                if (key === 'seller123') {
                    deleteKeyModal.style.display = 'none';
                    
                    if (deleteProductNameSpan) {
                        deleteProductNameSpan.textContent = currentProductName;
                    }
                    
                    if (deletePromotionIdInput) {
                        deletePromotionIdInput.value = currentPromotionId;
                    }
                    
                    confirmDeleteModal.style.display = 'flex';
                    deleteSellerKeyInput.value = '';
                } else {
                    alert('Невалиден код на продавача. Моля опитайте отново.');
                }
            });
            
                sellerKeyInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    verifyKeyBtn.click();
                }
            });
            
            deleteSellerKeyInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    verifyDeleteKeyBtn.click();
                }
            });
            
            deleteButtons.forEach(button => {
                button.addEventListener('click', function() {
                    currentPromotionId = this.getAttribute('data-id');
                    currentProductName = this.getAttribute('data-product');
                    
                    deleteKeyModal.style.display = 'flex';
                    setTimeout(() => {
                        deleteSellerKeyInput.focus();
                    }, 100);
                });
            });
            
                window.addEventListener('click', function(event) {
                if (event.target === keyModal) {
                    keyModal.style.display = 'none';
                    sellerKeyInput.value = '';
                }
                
                if (event.target === deleteKeyModal) {
                    deleteKeyModal.style.display = 'none';
                    deleteSellerKeyInput.value = '';
                }
                
                if (event.target === promotionModal) {
                    promotionModal.style.display = 'none';
                }
                
                if (event.target === confirmDeleteModal) {
                    confirmDeleteModal.style.display = 'none';
                }
            });
            
            const promotionForm = document.getElementById('promotionForm');
            if (promotionForm) {
                promotionForm.addEventListener('submit', function(e) {
                    const productName = document.getElementById('product_name').value.trim();
                    const discount = document.getElementById('discount').value.trim();
                    const store = document.getElementById('store').value.trim();
                    
                    if (!productName || !discount || !store) {
                        e.preventDefault();
                        alert('Моля попълнете всички полета.');
                    }
                });
            }
        });
    </script>
</body>
</html>
