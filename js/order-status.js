const orderIdElement = document.getElementById('orderId');
const orderTableElement = document.getElementById('orderTable');
const orderPaymentElement = document.getElementById('orderPayment');
const orderNotesElement = document.getElementById('orderNotes');
const orderTotalElement = document.getElementById('orderTotal');
const orderItemsElement = document.getElementById('orderItems');
const statusIcon = document.getElementById('statusIcon');
const statusTitle = document.getElementById('statusTitle');
const statusDescription = document.getElementById('statusDescription');
const cartBadge = document.getElementById('cartBadge');
const orderNumber = new URLSearchParams(window.location.search).get('order');
const successHeading = document.querySelector('.success-section h1');
const orderPageMessage = document.getElementById('orderPageMessage');

const orderStatuses = {
    Menunggu: {
        description: 'Tempahan anda telah diterima.',
        icon: 'bi-clock'
    },
    'Sedang Disediakan': {
        description: 'Tempahan anda sedang disediakan.',
        icon: 'bi-fire'
    },
    'Sedia Diambil': {
        description: 'Tempahan anda sedia untuk diambil.',
        icon: 'bi-check-circle'
    },
    Diserahkan: {
        description: 'Tempahan telah diserahkan.',
        icon: 'bi-bag-check'
    },
    Selesai: {
        description: 'Tempahan anda telah selesai.',
        icon: 'bi-check-circle-fill'
    },
    Dibatalkan: {
        description: 'Tempahan ini telah dibatalkan. Sila hubungi kakitangan kami jika anda perlukan bantuan.',
        icon: 'bi-x-circle'
    }
};

function updateCartBadge() {
    if (!cartBadge) return;

    const cart = JSON.parse(localStorage.getItem('cart') || '[]');
    const total = Array.isArray(cart)
        ? cart.reduce((count, item) => count + (Number(item.quantity) || 0), 0)
        : 0;
    cartBadge.textContent = String(total);
}

function formatPaymentMethod(method, status) {
    return `${method} — ${status}`;
}

function renderOrder(order) {
    const status = orderStatuses[order.status];
    if (!status) {
        throw new Error('Status tempahan daripada pelayan tidak dikenali.');
    }

    if (successHeading) {
        successHeading.textContent = order.status === 'Dibatalkan'
            ? 'Tempahan Dibatalkan'
            : 'Tempahan Berjaya!';
    }
    if (orderPageMessage) {
        orderPageMessage.textContent = order.status === 'Dibatalkan'
            ? 'Tempahan ini tidak lagi aktif.'
            : 'Status pesanan dikemas kini daripada sistem B@Bistro.';
    }

    orderIdElement.textContent = order.order_number;
    orderTableElement.textContent = `Meja ${order.table_number}`;
    orderPaymentElement.textContent = formatPaymentMethod(
        order.payment_method,
        order.payment_status
    );
    orderNotesElement.textContent = order.notes || 'Tiada komen';
    orderTotalElement.textContent = `RM ${Number(order.total).toFixed(2)}`;
    statusIcon.replaceChildren();

    const icon = document.createElement('i');
    icon.className = `bi ${status.icon}`;
    statusIcon.appendChild(icon);
    statusTitle.textContent = order.status;
    statusDescription.textContent = status.description;
    orderItemsElement.replaceChildren();

    order.items.forEach(item => {
        const row = document.createElement('div');
        row.className = 'summary-item';

        const imageWrapper = document.createElement('div');
        imageWrapper.className = 'summary-image';

        const image = document.createElement('img');
        image.src = item.image || 'images/foods/category/makanan.png';
        image.alt = item.name;
        imageWrapper.appendChild(image);

        const info = document.createElement('div');
        info.className = 'summary-info';

        const name = document.createElement('h3');
        name.textContent = item.name;

        const quantity = document.createElement('span');
        quantity.textContent = `Kuantiti: ${item.quantity}`;
        info.append(name, quantity);

        const price = document.createElement('span');
        price.className = 'summary-price';
        price.textContent = `RM ${(Number(item.price) * Number(item.quantity)).toFixed(2)}`;

        row.append(imageWrapper, info, price);
        orderItemsElement.appendChild(row);
    });
}

async function refreshOrder() {
    const response = await fetch(
        `api/orders.php?order=${encodeURIComponent(orderNumber)}`,
        { headers: { Accept: 'application/json' }, cache: 'no-store' }
    );
    const result = await response.json();

    if (!response.ok) {
        throw new Error(result.error || 'Status tempahan tidak dapat dimuatkan.');
    }

    renderOrder(result.order);
    return !['Selesai', 'Dibatalkan'].includes(result.order.status);
}

function showOrderError(message) {
    if (successHeading) successHeading.textContent = 'Maklumat Tempahan';
    if (orderPageMessage) orderPageMessage.textContent = '';
    orderIdElement.textContent = 'Tiada Tempahan';
    statusTitle.textContent = 'Tidak dapat memuatkan tempahan';
    statusDescription.textContent = message;
    orderItemsElement.textContent = 'Tiada maklumat item untuk dipaparkan.';
}

let statusPolling;

async function initializeOrderStatus() {
    updateCartBadge();

    if (!orderNumber) {
        showOrderError('Nombor tempahan tidak diberikan.');
        return;
    }

    try {
        const shouldContinuePolling = await refreshOrder();
        if (shouldContinuePolling) {
            statusPolling = setInterval(() => {
                refreshOrder().then(shouldContinue => {
                    if (!shouldContinue) clearInterval(statusPolling);
                }).catch(error => {
                    statusDescription.textContent = error.message;
                });
            }, 5000);
        }
    } catch (error) {
        showOrderError(error.message);
    }
}

initializeOrderStatus();
