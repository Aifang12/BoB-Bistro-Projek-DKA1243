const cartContainer = document.getElementById('cartContainer');
const cartSummary = document.getElementById('cartSummary');
const totalItemsElement = document.getElementById('totalItems');
const cartTotalElement = document.getElementById('cartTotal');
const checkoutBtn = document.getElementById('checkoutBtn');

let menuLoaded = false;

function getCart() {
    const cart = JSON.parse(localStorage.getItem('cart') || '[]');
    if (!Array.isArray(cart)) {
        throw new Error('Data bakul tidak sah. Sila kosongkan bakul dan cuba semula.');
    }
    return cart;
}

function saveCart(cart) {
    localStorage.setItem('cart', JSON.stringify(cart));
}

function createCartItem(item) {
    const article = document.createElement('article');
    article.className = 'cart-item';
    article.dataset.id = String(item.id);

    const imageWrapper = document.createElement('div');
    imageWrapper.className = 'cart-item-image';
    const image = document.createElement('img');
    image.src = item.image || 'images/foods/category/makanan.png';
    image.alt = item.name || 'Menu';
    imageWrapper.appendChild(image);

    const info = document.createElement('div');
    info.className = 'cart-item-info';
    const name = document.createElement('h3');
    name.className = 'cart-item-name';
    name.textContent = item.name || 'Menu tidak tersedia';

    const price = document.createElement('p');
    price.className = 'cart-item-price';
    price.textContent = item.unavailable
        ? 'Menu tidak tersedia'
        : `RM ${Number(item.price).toFixed(2)}`;
    info.append(name, price);

    if (!item.unavailable) {
        const quantity = document.createElement('div');
        quantity.className = 'cart-quantity';

        const minus = document.createElement('button');
        minus.className = 'quantity-minus';
        minus.type = 'button';
        minus.setAttribute('aria-label', `Kurangkan ${item.name}`);
        minus.innerHTML = '<i class="bi bi-dash" aria-hidden="true"></i>';

        const count = document.createElement('span');
        count.textContent = String(item.quantity);

        const plus = document.createElement('button');
        plus.className = 'quantity-plus';
        plus.type = 'button';
        plus.setAttribute('aria-label', `Tambah ${item.name}`);
        plus.innerHTML = '<i class="bi bi-plus" aria-hidden="true"></i>';

        quantity.append(minus, count, plus);
        info.appendChild(quantity);
    }

    const remove = document.createElement('button');
    remove.className = 'remove-cart-btn';
    remove.type = 'button';
    remove.title = 'Buang item';
    remove.setAttribute('aria-label', `Buang ${item.name || 'item'} dari bakul`);
    remove.innerHTML = '<i class="bi bi-trash3" aria-hidden="true"></i>';

    article.append(imageWrapper, info, remove);
    return article;
}

function renderCart() {
    const cart = getCart();
    cartContainer.replaceChildren();

    if (cart.length === 0) {
        const emptyState = document.createElement('div');
        emptyState.className = 'empty-cart';
        emptyState.innerHTML = `
            <i class="bi bi-cart-x"></i>
            <h2>Bakul Anda Kosong</h2>
            <p>Anda belum menambah sebarang makanan.</p>
            <a href="menu.html" class="btn btn-primary">Lihat Menu</a>
        `;
        cartContainer.appendChild(emptyState);
        cartSummary.style.display = 'none';
        checkoutBtn.disabled = true;
        return;
    }

    cartSummary.style.display = 'block';
    let totalItems = 0;
    let totalPrice = 0;
    const hasUnavailableItem = cart.some(item => item.unavailable);

    cart.forEach(item => {
        cartContainer.appendChild(createCartItem(item));
        totalItems += Number(item.quantity) || 0;
        if (!item.unavailable) {
            totalPrice += Number(item.price) * Number(item.quantity);
        }
    });

    totalItemsElement.textContent = String(totalItems);
    cartTotalElement.textContent = `RM ${totalPrice.toFixed(2)}`;
    checkoutBtn.disabled = !menuLoaded || hasUnavailableItem;

    if (hasUnavailableItem) {
        const message = document.createElement('p');
        message.className = 'no-result';
        message.textContent = 'Buang menu yang tidak tersedia sebelum meneruskan checkout.';
        cartContainer.prepend(message);
    }
}

function changeQuantity(id, amount) {
    const cart = getCart();
    const item = cart.find(entry => Number(entry.id) === id);
    if (!item || item.unavailable) return;

    item.quantity = Number(item.quantity) + amount;
    if (item.quantity <= 0) {
        saveCart(cart.filter(entry => Number(entry.id) !== id));
    } else if (item.quantity <= 99) {
        saveCart(cart);
    }
    renderCart();
}

function removeItem(id) {
    saveCart(getCart().filter(item => Number(item.id) !== id));
    renderCart();
}

cartContainer.addEventListener('click', event => {
    const cartItem = event.target.closest('.cart-item');
    if (!cartItem) return;

    const id = Number(cartItem.dataset.id);
    if (event.target.closest('.quantity-plus')) changeQuantity(id, 1);
    if (event.target.closest('.quantity-minus')) changeQuantity(id, -1);
    if (event.target.closest('.remove-cart-btn')) removeItem(id);
});

checkoutBtn.addEventListener('click', () => {
    if (checkoutBtn.disabled || getCart().length === 0) return;
    window.location.href = 'checkout.html';
});

async function initializeCart() {
    checkoutBtn.disabled = true;
    try {
        await loadFoods();
        const cart = getCart().map(storedItem => {
            const item = storedItem && typeof storedItem === 'object'
                ? storedItem
                : { name: 'Item bakul tidak sah', quantity: 0 };
            const current = foods.find(food => food.id === Number(item.id));
            if (!current || !Number.isInteger(Number(item.quantity))
                || Number(item.quantity) < 1 || Number(item.quantity) > 99
            ) {
                return { ...item, unavailable: true };
            }

            return {
                id: current.id,
                name: current.name,
                price: current.price,
                image: current.image,
                quantity: Number(item.quantity)
            };
        });

        saveCart(cart);
        menuLoaded = true;
        renderCart();
    } catch (error) {
        cartContainer.textContent = error.message;
        cartSummary.style.display = 'none';
    }
}

initializeCart();
