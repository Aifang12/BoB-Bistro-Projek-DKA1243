let foods = [];
let menuCategories = [];

async function loadFoods() {
    const response = await fetch('api/menu.php', {
        headers: { Accept: 'application/json' },
        cache: 'no-store'
    });

    let result;
    try {
        result = await response.json();
    } catch (error) {
        throw new Error('Respons menu daripada pelayan tidak sah.');
    }

    if (!response.ok) {
        throw new Error(result.error || 'Menu tidak dapat dimuatkan.');
    }

    foods = result.items;
    menuCategories = result.categories;
    return foods;
}

function createFoodCard(food) {
    const card = document.createElement('article');
    card.className = 'food-card';
    card.dataset.id = String(food.id);

    const image = document.createElement('img');
    image.src = food.image;
    image.alt = food.name;
    card.appendChild(image);

    const info = document.createElement('div');
    info.className = 'food-info';

    const name = document.createElement('h3');
    name.className = 'food-title';
    name.textContent = food.name;

    const category = document.createElement('p');
    category.className = 'food-category';
    category.textContent = food.category;

    const bottom = document.createElement('div');
    bottom.className = 'food-bottom';

    const price = document.createElement('span');
    price.className = 'food-price';
    price.textContent = `RM ${Number(food.price).toFixed(2)}`;

    const addButton = document.createElement('button');
    addButton.className = 'add-btn';
    addButton.type = 'button';
    addButton.setAttribute('aria-label', `Lihat ${food.name}`);
    addButton.innerHTML = '<i class="bi bi-plus" aria-hidden="true"></i>';

    bottom.append(price, addButton);
    info.append(name, category, bottom);
    card.appendChild(info);
    return card;
}

function initializeTableSession() {
    const urlParams = new URLSearchParams(window.location.search);
    const tableFromQR = urlParams.get('table');

    if (!tableFromQR) return;

    const tableNumber = Number(tableFromQR);
    if (Number.isInteger(tableNumber) && tableNumber > 0) {
        localStorage.setItem('activeTable', tableNumber);
    }

    window.history.replaceState(
        {},
        document.title,
        window.location.pathname
    );
}

function getActiveTable() {
    return localStorage.getItem('activeTable');
}

window.loadFoods = loadFoods;
window.createFoodCard = createFoodCard;
window.getActiveTable = getActiveTable;

initializeTableSession();
