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

        popularFoods.innerHTML += `

        <article
            class="food-card"
            data-id="${food.id}">

            <img
                src="${food.image}"
                alt="${food.name}">

            <div class="food-info">

                <h3 class="food-title">

                    ${food.name}

                </h3>

                <p class="food-category">

                    ${food.category}

                </p>

                <div class="food-bottom">

                    <span class="food-price">

                        RM ${food.price.toFixed(2)}

                    </span>

                    <button class="add-btn">

                        <i class="bi bi-plus"></i>

                    </button>

                </div>

            </div>

        </article>

        `;

    });

}

/*==========================================
    CLICK PRODUCT
==========================================*/

popularFoods.addEventListener("click", (event) => {

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

renderPopularFoods();

updateCartBadge();