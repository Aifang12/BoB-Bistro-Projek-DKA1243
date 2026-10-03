/*==========================================
    DOM
==========================================*/

const popularFoods = document.getElementById("popularFoods");
const cartBadge = document.getElementById("cartBadge");

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

    if (!cartBadge) return;

    const cart =
        JSON.parse(localStorage.getItem("cart")) || [];

    let total = 0;

    cart.forEach(item => {

        total += item.quantity;

    });

    cartBadge.textContent = total;

}

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