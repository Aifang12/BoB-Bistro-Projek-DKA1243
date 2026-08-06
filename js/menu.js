/*==========================================
    MENU PAGE
==========================================*/

const urlParams =
new URLSearchParams(window.location.search);

const categoryFromURL =
urlParams.get("category");

const menuContainer = document.getElementById("menuContainer");
const searchInput = document.getElementById("searchInput");
const categoryButtons = document.querySelectorAll(".category-btn");

let currentCategory = "Semua";

/*==========================================
    RENDER MENU
==========================================*/

function loadMenu(data) {

    menuContainer.innerHTML = "";

    if (data.length === 0) {

        menuContainer.innerHTML = `
            <p class="no-result">
                Tiada makanan dijumpai.
            </p>
        `;

        return;

    }

    data.forEach(food => {

        menuContainer.innerHTML += `

        <article class="food-card" data-id="${food.id}">

            <img src="${food.image}" alt="${food.name}">

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
    FILTER MENU
==========================================*/

function filterMenu() {

    const keyword = searchInput.value.toLowerCase();

    const filtered = foods.filter(food => {

        const matchCategory =
            currentCategory === "Semua"
            ||
            food.category.toLowerCase() === currentCategory.toLowerCase();

        const matchSearch =
            food.name.toLowerCase().includes(keyword);

        return matchCategory && matchSearch;

    });

    loadMenu(filtered);

}

function goToProduct(id){

    window.location.href =
    `product.html?id=${id}`;

}
/*==========================================
    SEARCH
==========================================*/

searchInput.addEventListener("input", filterMenu);

/*==========================================
    CATEGORY
==========================================*/

categoryButtons.forEach(button => {

    button.addEventListener("click", () => {

        categoryButtons.forEach(btn => {

            btn.classList.remove("active");

        });

        button.classList.add("active");

        currentCategory = button.textContent.trim();

        filterMenu();

    });

});
/*==========================================
    INITIALIZE
==========================================*/

if (categoryFromURL) {

    currentCategory = categoryFromURL;

    categoryButtons.forEach(button => {

        if (button.textContent.trim() === categoryFromURL) {

            button.classList.add("active");

        } else {

            button.classList.remove("active");

        }

    });

    filterMenu();

} else {

    loadMenu(foods);

}

/*==========================================
    CLICK PRODUCT
==========================================*/

menuContainer.addEventListener("click",(event)=>{

    const card=event.target.closest(".food-card");

    if(!card) return;

    const id=card.dataset.id;

    window.location.href=
    `product.html?id=${id}`;

});