<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Returns — teepsaa</title>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/returns/returns.css">
</head>
<body>
<?php require __DIR__ . '/../header/header.php'; ?>
<main>
    <div class="returns-wrap">
<?php if ($lang === 'km'): ?>
        <h1>ការប្រគល់ទំនិញ</h1>

        <div class="returns-section">
            <h2>លក្ខណៈសម្បត្តិសម្រាប់ការប្រគល់ទំនិញ</h2>
            <p>ការប្រគល់ទំនិញត្រូវបានដោះស្រាយរវាងអ្នកទិញ និងអ្នកលក់ម្នាក់ៗ។ សូមទាក់ទងអ្នកលក់ដោយផ្ទាល់ ប្រសិនបើអ្នកមានបញ្ហាជាមួយកម្មង់របស់អ្នក។</p>
        </div>

        <div class="returns-section">
            <h2>វិវាទ</h2>
            <p>ប្រសិនបើអ្នកមិនអាចដោះស្រាយបញ្ហាជាមួយអ្នកលក់ សូមទាក់ទងជំនួយ ទីផ្សារ ហើយយើងនឹងជួយសម្រុះសម្រួលវិវាទ។</p>
        </div>

        <div class="returns-section">
            <h2>សំណង</h2>
            <p>លក្ខណៈសម្បត្តិសម្រាប់សំណងត្រូវបានកំណត់ជាករណីៗ។ ទីផ្សារ មិនធានាសំណងសម្រាប់ការទិញទាំងអស់ទេ។ សូមពិនិត្យបញ្ជីរបស់អ្នកលក់ដោយប្រុងប្រយ័ត្នមុននឹងកម្មង់។</p>
        </div>
<?php else: ?>
        <h1>Returns</h1>

        <div class="returns-section">
            <h2>Return eligibility</h2>
            <p>Returns are handled between the buyer and the individual vendor. Contact the vendor directly if you have an issue with your order.</p>
        </div>

        <div class="returns-section">
            <h2>Disputes</h2>
            <p>If you are unable to resolve an issue with a vendor, contact teepsaa support and we will assist in mediating the dispute.</p>
        </div>

        <div class="returns-section">
            <h2>Refunds</h2>
            <p>Refund eligibility is determined on a case-by-case basis. teepsaa does not guarantee refunds for all purchases. Please review a vendor's listing carefully before ordering.</p>
        </div>
<?php endif; ?>
    </div>
</main>
<?php require __DIR__ . '/../footer/footer.php'; ?>
</body>
</html>
