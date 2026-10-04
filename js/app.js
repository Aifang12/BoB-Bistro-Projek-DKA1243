/*==========================================
    DOM
==========================================*/

const popularFoods = document.getElementById("popularFoods");
const cartBadge = document.getElementById("cartBadge");
const siteMenu = document.getElementById("siteMenu");
const siteMenuToggle = document.getElementById("siteMenuToggle");
const siteMenuClose = document.getElementById("siteMenuClose");
const activeTable = Number(localStorage.getItem("activeTable"));

if (Number.isInteger(activeTable) && activeTable > 0) {
    document.body.classList.add("table-ordering");
}

/*==========================================
    POPULAR MENU
==========================================*/

function renderPopularFoods() {

    if (!popularFoods) return;

    popularFoods.innerHTML = "";

    const popular = foods.slice(0, 4);

    popular.forEach(food => {
        popularFoods.appendChild(createFoodCard(food));
    });

}

/*==========================================
    CLICK PRODUCT
==========================================*/

popularFoods?.addEventListener("click", (event) => {

    const card = event.target.closest(".food-card");

    if (!card) return;

    const id = card.dataset.id;

    window.location.href =
        `product.html?id=${id}`;

});

/*==========================================
    CATEGORY
==========================================*/

function goToCategory(category) {

    window.location.href =
        `menu.html?category=${category}`;

}

window.goToCategory = goToCategory;

/*==========================================
    CART BADGE
==========================================*/

function updateCartBadge() {

    const cart =
        JSON.parse(localStorage.getItem("cart")) || [];

    let total = 0;

    cart.forEach(item => {

        total += item.quantity;

    });

    if (cartBadge) cartBadge.textContent = total;

    const siteMenuCartCount = document.getElementById("siteMenuCartCount");
    if (siteMenuCartCount) siteMenuCartCount.textContent = total;

}

/*==========================================
    SITE NAVIGATION
==========================================*/

siteMenuToggle?.addEventListener("click", () => {
    if (siteMenu && !siteMenu.open) {
        siteMenu.showModal();
        siteMenuToggle.setAttribute("aria-expanded", "true");
        siteMenuToggle.setAttribute("aria-label", "Tutup menu navigasi");
    }
});

siteMenuClose?.addEventListener("click", () => {
    siteMenu?.close();
});

siteMenu?.addEventListener("click", event => {
    if (event.target === siteMenu) siteMenu.close();
});

siteMenu?.addEventListener("keydown", event => {
    if (event.key === "Escape") {
        event.preventDefault();
        siteMenu.close();
    }
});

siteMenu?.addEventListener("close", () => {
    siteMenuToggle?.setAttribute("aria-expanded", "false");
    siteMenuToggle?.setAttribute("aria-label", "Buka menu navigasi");
    siteMenuToggle?.focus();
});

/*==========================================
    INITIALIZE
==========================================*/

loadFoods()
    .then(renderPopularFoods)
    .catch(error => {
        if (popularFoods) {
            popularFoods.textContent = error.message;
        }
    });

updateCartBadge();