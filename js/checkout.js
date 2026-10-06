const checkoutItems = document.getElementById('checkoutItems');
const checkoutItemCount = document.getElementById('checkoutItemCount');
const checkoutTotal = document.getElementById('checkoutTotal');
const orderNotes = document.getElementById('orderNotes');
const placeOrderBtn = document.getElementById('placeOrderBtn');
const checkoutTable = document.getElementById('checkoutTable');
const checkoutError = document.getElementById('checkoutError');
const checkoutLoading = document.getElementById('checkoutLoading');
const ewalletChoice = document.getElementById('paymentEwallet');
const ewalletMethods = document.getElementById('ewalletMethods');

function syncEwalletOptions() {
    const isEwalletSelected = ewalletChoice.checked;
    ewalletMethods.hidden = !isEwalletSelected;
    ewalletChoice.setAttribute('aria-expanded', String(isEwalletSelected));
}

function getSelectedPaymentMethod() {
    const selectedChoice = document.querySelector('input[name="paymentChoice"]:checked')?.value;
    if (!selectedChoice) {
        return null;
    }

    if (selectedChoice === 'E-wallet') {
        return document.querySelector('input[name="ewalletProvider"]:checked')?.value || null;
    }

    return selectedChoice;
}

function getCart() {
    const cart = JSON.parse(localStorage.getItem('cart') || '[]');
    if (!Array.isArray(cart)) {
        throw new Error('Data bakul tidak sah. Sila kosongkan bakul dan cuba semula.');
    }
    return cart;
}

function getActiveTable() {
    const tableNumber = Number(localStorage.getItem('activeTable'));
    return Number.isInteger(tableNumber) && tableNumber > 0
        ? tableNumber
        : null;
}

function showCheckoutError(message) {
    checkoutError.textContent = message;
    checkoutError.hidden = false;
}

function createCheckoutItem(item, unavailable = false) {
    const article = document.createElement('article');
    article.className = 'checkout-item';

    const imageWrapper = document.createElement('div');
    imageWrapper.className = 'checkout-item-image';

    const image = document.createElement('img');
    image.src = item.image || 'images/foods/category/makanan.png';
    image.alt = item.name || 'Menu';
    imageWrapper.appendChild(image);

    const info = document.createElement('div');
    info.className = 'checkout-item-info';

    const name = document.createElement('h3');
    name.className = 'checkout-item-name';
    name.textContent = item.name || 'Menu tidak tersedia';

    const quantity = document.createElement('span');
    quantity.className = 'checkout-item-quantity';
    quantity.textContent = `Kuantiti: ${item.quantity}`;
    info.append(name, quantity);

    const total = document.createElement('span');
    total.className = 'checkout-item-total';
    total.textContent = unavailable
        ? 'Tidak tersedia'
        : `RM ${(item.price * item.quantity).toFixed(2)}`;

    article.append(imageWrapper, info, total);
    return article;
}

function loadCheckout() {
    const cart = getCart();
    checkoutItems.replaceChildren();

    const tableNumber = getActiveTable();
    checkoutTable.textContent = tableNumber
        ? `Meja ${tableNumber}`
        : 'Meja tidak dikenal pasti';

    if (cart.length === 0) {
        checkoutItems.textContent = 'Bakul anda kosong. Sila tambah menu sebelum membuat tempahan.';
        placeOrderBtn.disabled = true;
        return;
    }

    let totalItems = 0;
    let totalPrice = 0;
    let unavailableCount = 0;

    const refreshedCart = cart.map(cartItem => {
        if (!cartItem || typeof cartItem !== 'object') {
            unavailableCount += 1;
            checkoutItems.appendChild(createCheckoutItem(
                { name: 'Item bakul tidak sah', quantity: 0 },
                true
            ));
            return { id: null, name: 'Item bakul tidak sah', quantity: 0, unavailable: true };
        }

        const itemId = Number(cartItem.id);
        const quantity = Number(cartItem.quantity);
        const currentItem = foods.find(food => food.id === itemId);

        if (!Number.isInteger(itemId) || !Number.isInteger(quantity) || quantity < 1) {
            unavailableCount += 1;
            checkoutItems.appendChild(createCheckoutItem(cartItem, true));
            return cartItem;
        }

        if (!currentItem) {
            unavailableCount += 1;
            checkoutItems.appendChild(createCheckoutItem(cartItem, true));
            return cartItem;
        }

        const refreshedItem = {
            id: currentItem.id,
            name: currentItem.name,
            price: currentItem.price,
            image: currentItem.image,
            quantity
        };

        totalItems += quantity;
        totalPrice += currentItem.price * quantity;
        checkoutItems.appendChild(createCheckoutItem(refreshedItem));
        return refreshedItem;
    });

    localStorage.setItem('cart', JSON.stringify(refreshedCart));
    checkoutItemCount.textContent = String(totalItems);
    checkoutTotal.textContent = `RM ${totalPrice.toFixed(2)}`;

    if (unavailableCount > 0) {
        showCheckoutError('Bakul mengandungi item yang tidak tersedia atau tidak sah. Sila buang item tersebut dari bakul.');
    }
    if (!tableNumber) {
        showCheckoutError('Sesi meja tidak ditemui. Sila imbas QR Code meja sebelum membuat tempahan.');
    }

    placeOrderBtn.disabled = unavailableCount > 0 || !tableNumber;
}

async function placeOrder() {
    checkoutError.hidden = true;

    let cart;
    try {
        cart = getCart();
    } catch (error) {
        showCheckoutError(error.message);
        return;
    }
    const tableNumber = getActiveTable();
    const selectedChoice = document.querySelector('input[name="paymentChoice"]:checked')?.value;
    const paymentMethod = getSelectedPaymentMethod();

    if (!tableNumber || cart.length === 0 || !selectedChoice || !paymentMethod) {
        if (selectedChoice === 'E-wallet' && !paymentMethod) {
            showCheckoutError('Sila pilih penyedia E-wallet sebelum membuat tempahan.');
            return;
        }
        showCheckoutError('Sila semak meja, bakul dan kaedah pembayaran sebelum membuat tempahan.');
        return;
    }

    placeOrderBtn.disabled = true;
    placeOrderBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Memproses...';
    checkoutLoading.hidden = false;
    checkoutLoading.setAttribute('aria-hidden', 'false');
    document.body.setAttribute('aria-busy', 'true');

    try {
        const response = await fetch('api/orders.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({
                table_number: tableNumber,
                payment_method: paymentMethod,
                notes: orderNotes.value.trim(),
                items: cart.map(item => ({
                    id: Number(item.id),
                    quantity: Number(item.quantity)
                }))
            })
        });
        const result = await response.json();

        if (!response.ok) {
            throw new Error(result.error || 'Tempahan tidak dapat disimpan.');
        }

        localStorage.removeItem('cart');
        window.location.href = `order-status.html?order=${encodeURIComponent(result.order.order_number)}`;
    } catch (error) {
        checkoutLoading.hidden = true;
        checkoutLoading.setAttribute('aria-hidden', 'true');
        document.body.removeAttribute('aria-busy');
        showCheckoutError(error.message || 'Tempahan tidak dapat disimpan. Sila cuba lagi.');
        placeOrderBtn.disabled = false;
        placeOrderBtn.innerHTML = '<i class="bi bi-check-circle"></i> Buat Tempahan';
    }
}

document.querySelectorAll('input[name="paymentChoice"]').forEach(input => {
    input.addEventListener('change', syncEwalletOptions);
});

syncEwalletOptions();
placeOrderBtn.addEventListener('click', placeOrder);

async function initializeCheckout() {
    placeOrderBtn.disabled = true;
    checkoutItems.textContent = 'Ringkasan pesanan sedang dimuatkan...';

    try {
        await loadFoods();
        loadCheckout();
    } catch (error) {
        checkoutItems.textContent = 'Menu tidak dapat dimuatkan.';
        showCheckoutError(error.message);
    }
}

initializeCheckout();
