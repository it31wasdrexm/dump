<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/checkout.css">
    <title>Форма оплаты</title>
</head>
<body>

<div class="checkout-container">
    <form id="paymentForm" action="orders.php" method="post" class="form-section">
        <h2>Данные доставки</h2>
        
        <label for="country">Страна</label>
        <select id="country" name="country" required>
            <option value="">Выберите страну</option>
            <option value="Kazakhstan">Казахстан</option>
            <option value="Russia">Россия</option>
            <option value="Belarus">Беларусь</option>
        </select>

        <label for="city">Город</label>
        <input type="text" id="city" name="city" required>

        <label for="address">Адрес</label>
        <input type="text" id="address" name="address" required>

        <div class="form-row">
            <div>
                <label for="email">Электронная почта</label>
                <input type="email" id="email" name="email" placeholder="example@mail.com" required>
                <div class="error-message" id="email-error">Введите корректный email (должен содержать @)</div>
            </div>
            <div>
                <label for="phone">Телефон</label>
                <input type="tel" id="phone" name="phone" placeholder="+7 (XXX) XXX-XX-XX" required>
                <div class="error-message" id="phone-error">Номер должен начинаться с +7</div>
            </div>
        </div>
    </div>
    
    <div class="payment-section">
        <h2>Платежные данные</h2>
        
        <label for="paymentMethod">Способ оплаты</label>
        <select id="paymentMethod" name="paymentMethod" required>
            <option value="">Выберите способ оплаты</option>
            <option value="visa">Visa</option>
            <option value="mastercard">MasterCard</option>
            <option value="mir">Мир</option>
        </select>

        <label for="cardNumber">Номер карты</label>
        <input type="text" id="cardNumber" name="cardNumber" placeholder="XXXX XXXX XXXX XXXX" required>
        <div class="error-message" id="card-error">Введите номер карты через пробел (16 цифр)</div>

        <label for="cardholderName">Держатель карты</label>
        <input type="text" id="cardholderName" name="cardholderName" placeholder="IVAN IVANOV" required>
        <div class="error-message" id="name-error">Введите имя и фамилию через пробел</div>

        <div class="form-row">
            <div>
                <label for="cardDate">Срок действия</label>
                <input type="text" id="cardDate" name="cardDate" placeholder="ММ/ГГ" required>
                <div class="error-message" id="date-error">Формат: ММ/ГГ (например 02/27)</div>
            </div>
            <div>
                <label for="cvv">CVV</label>
                <input type="text" id="cvv" name="cvv" placeholder="123" maxlength="3" required>
                <div class="error-message" id="cvv-error">Введите 3 цифры</div>
            </div>
        </div>

        <button type="submit">Оплатить</button>
    </form>
</div>

<script>
    document.getElementById('paymentForm').addEventListener('submit', function(e) {
        let isValid = true;
        
       
        const email = document.getElementById('email');
        if (!email.value.includes('@')) {
            document.getElementById('email-error').style.display = 'block';
            isValid = false;
        } else {
            document.getElementById('email-error').style.display = 'none';
        }
        
       
        const phone = document.getElementById('phone');
        if (!phone.value.startsWith('+7')) {
            document.getElementById('phone-error').style.display = 'block';
            isValid = false;
        } else {
            document.getElementById('phone-error').style.display = 'none';
        }
        
       
        const cardNumber = document.getElementById('cardNumber');
        if (!/^\d{4} \d{4} \d{4} \d{4}$/.test(cardNumber.value)) {
            document.getElementById('card-error').style.display = 'block';
            isValid = false;
        } else {
            document.getElementById('card-error').style.display = 'none';
        }
        
       
        const cardholderName = document.getElementById('cardholderName');
        if (!cardholderName.value.includes(' ')) {
            document.getElementById('name-error').style.display = 'block';
            isValid = false;
        } else {
            document.getElementById('name-error').style.display = 'none';
        }
        
        
        const cardDate = document.getElementById('cardDate');
        if (!/^\d{2}\/\d{2}$/.test(cardDate.value)) {
            document.getElementById('date-error').style.display = 'block';
            isValid = false;
        } else {
            document.getElementById('date-error').style.display = 'none';
        }
        
       
        const cvv = document.getElementById('cvv');
        if (!/^\d{3}$/.test(cvv.value)) {
            document.getElementById('cvv-error').style.display = 'block';
            isValid = false;
        } else {
            document.getElementById('cvv-error').style.display = 'none';
        }
        
        if (!isValid) {
            e.preventDefault();
        }
    });

   
    document.getElementById('phone').addEventListener('input', function(e) {
        let value = this.value.replace(/\D/g, '');
        if (value.length > 0) {
            value = '+7' + value.substring(1);
        }
        this.value = value;
    });

   
    document.getElementById('cardNumber').addEventListener('input', function(e) {
        let value = this.value.replace(/\D/g, '');
        let formatted = '';
        for (let i = 0; i < value.length && i < 16; i++) {
            if (i > 0 && i % 4 === 0) formatted += ' ';
            formatted += value[i];
        }
        this.value = formatted;
    });

    
    document.getElementById('cardDate').addEventListener('input', function(e) {
        let value = this.value.replace(/\D/g, '');
        if (value.length > 2) {
            value = value.substring(0, 2) + '/' + value.substring(2, 4);
        }
        this.value = value;
    });

   
    document.getElementById('cvv').addEventListener('input', function(e) {
        this.value = this.value.replace(/\D/g, '').substring(0, 3);
    });
</script>

</body>
</html>