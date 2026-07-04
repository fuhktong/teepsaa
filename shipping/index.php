<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shipping — teepsaa</title>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/shipping/shipping.css">
</head>
<body>
<?php require __DIR__ . '/../header/header.php'; ?>
<main>
    <div class="shipping-wrap">
<?php if ($lang === 'km'): ?>
        <h1>ការដឹកជញ្ជូន</h1>

        <div class="shipping-section">
            <h2>ការដឹកជញ្ជូន</h2>
            <p>ទីផ្សារ ប្រើ Grab សម្រាប់ការដឹកជញ្ជូននៅក្នុងភ្នំពេញ។ ការដឹកជញ្ជូនគឺជាការបង់ប្រាក់ពេលដឹកជញ្ជូន (COD) — អ្នកបង់ប្រាក់ឱ្យអ្នកបើកបរដោយផ្ទាល់នៅពេលកម្មង់របស់អ្នកមកដល់។ ថ្លៃដឹកជញ្ជូនប៉ាន់ស្មានត្រូវបានបង្ហាញនៅក្នុងរទេះ និងពេលបង់ប្រាក់សម្រាប់ជាឯកសារយោង។</p>
        </div>

        <div class="shipping-section">
            <h2>តំបន់ដឹកជញ្ជូន</h2>
            <p>ការដឹកជញ្ជូនបច្ចុប្បន្នមានតែនៅក្នុងភ្នំពេញប៉ុណ្ណោះ។ កម្មង់ពីអ្នកលក់នៅក្រៅតំបន់ដឹកជញ្ជូនមិនអាចបញ្ចប់បានទេ។</p>
        </div>

        <div class="shipping-section">
            <h2>ការបង់ប្រាក់</h2>
            <p>ទីផ្សារ ទទួលយកការបង់ប្រាក់តាមការផ្ទេរប្រាក់ធនាគារ ABA។ បន្ទាប់ពីដាក់កម្មង់ សូមស្កេនកូដ QR ក្នុងកម្មវិធី ABA របស់អ្នក ហើយដាក់ស្នើការបង់ប្រាក់។ កម្មង់ត្រូវបានដំណើរការនៅពេលការបង់ប្រាក់ត្រូវបានបញ្ជាក់ដោយក្រុមការងាររបស់យើង។</p>
        </div>

        <div class="shipping-section">
            <h2>គោលការណ៍ទីផ្សារ</h2>
            <p>ទីផ្សារ គឺជាទីផ្សារភ្ជាប់អ្នកទិញ និងអ្នកលក់ឯករាជ្យ។ អ្នកលក់នីមួយៗទទួលខុសត្រូវចំពោះគុណភាព និងភាពត្រឹមត្រូវនៃបញ្ជីរបស់ពួកគេ។ ទីផ្សារ មិនទទួលខុសត្រូវចំពោះស្ថានភាពនៃទំនិញដែលលក់ដោយអ្នកលក់ទេ។</p>
        </div>
<?php else: ?>
        <h1>Shipping</h1>

        <div class="shipping-section">
            <h2>Delivery</h2>
            <p>teepsaa uses Grab for deliveries within Phnom Penh. Delivery is cash on delivery (COD) — you pay the driver directly when your order arrives. The estimated delivery fee is shown in your cart and at checkout for reference.</p>
        </div>

        <div class="shipping-section">
            <h2>Delivery area</h2>
            <p>Deliveries are currently available within Phnom Penh only. Orders from vendors outside the delivery range cannot be completed.</p>
        </div>

        <div class="shipping-section">
            <h2>Payment</h2>
            <p>teepsaa accepts payment via ABA bank transfer. After placing your order, scan the QR code in your ABA app and submit your payment. Orders are processed once payment is confirmed by our team.</p>
        </div>

        <div class="shipping-section">
            <h2>Marketplace policy</h2>
            <p>teepsaa is a marketplace connecting buyers and independent vendors. Each vendor is responsible for the quality and accuracy of their listings. teepsaa is not responsible for the condition of items sold by vendors.</p>
        </div>
<?php endif; ?>
    </div>
</main>
<?php require __DIR__ . '/../footer/footer.php'; ?>
</body>
</html>
