const urlParams = new URLSearchParams(window.location.search);
const categoryFromURL = urlParams.get('category');
const menuContainer = document.getElementById('menuContainer');
const searchInput = document.getElementById('searchInput');
const categoryContainer = document.getElementById('categoryButtons');

let currentCategory = 'Semua';

function loadMenu(data) {
    menuContainer.replaceChildren();

    if (data.length === 0) {
        const noResult = document.createElement('p');
        noResult.className = 'no-result';
        noResult.textContent = 'Tiada makanan dijumpai.';
        menuContainer.appendChild(noResult);
        return;
    }

    data.forEach(food => menuContainer.appendChild(createFoodCard(food)));
}

function renderCategories() {
    categoryContainer.replaceChildren();
    const categories = ['Semua', ...menuCategories.map(category => category.name)];

    categories.forEach(category => {
        const button = document.createElement('button');
        button.className = 'category-btn';
        button.type = 'button';
        button.textContent = category;
        button.classList.toggle('active', category === currentCategory);
        categoryContainer.appendChild(button);
    });
}

function filterMenu() {
    const keyword = searchInput.value.trim().toLocaleLowerCase();
    const filtered = foods.filter(food => {
        const matchCategory =
            currentCategory === 'Semua' ||
            food.category.toLocaleLowerCase() === currentCategory.toLocaleLowerCase();
        const matchSearch =
            food.name.toLocaleLowerCase().includes(keyword) ||
            food.category.toLocaleLowerCase().includes(keyword);

        return matchCategory && matchSearch;
    });

    loadMenu(filtered);
}

function goToProduct(id) {
    window.location.href = `product.html?id=${encodeURIComponent(id)}`;
}

searchInput.addEventListener('input', filterMenu);

categoryContainer.addEventListener('click', event => {
    const button = event.target.closest('.category-btn');
    if (!button) return;

    currentCategory = button.textContent.trim();
    categoryContainer.querySelectorAll('.category-btn').forEach(categoryButton => {
        categoryButton.classList.toggle('active', categoryButton === button);
    });
    filterMenu();
});

menuContainer.addEventListener('click', event => {
    const card = event.target.closest('.food-card');
    if (card) goToProduct(card.dataset.id);
});

async function initializeMenu() {
    menuContainer.textContent = 'Menu sedang dimuatkan...';

    try {
        await loadFoods();
        const requestedCategory = menuCategories.find(
            category => category.name.toLocaleLowerCase() === (categoryFromURL || '').toLocaleLowerCase()
        );
        currentCategory = requestedCategory ? requestedCategory.name : 'Semua';
        renderCategories();
        filterMenu();
    } catch (error) {
        menuContainer.textContent = error.message;
    }
}

initializeMenu();
