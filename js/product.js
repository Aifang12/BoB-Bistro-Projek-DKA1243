/*==========================================
    DOM ELEMENTS
==========================================*/

const productImage = document.getElementById("productImage");
const productCategory = document.getElementById("productCategory");
const productName = document.getElementById("productName");
const productRating = document.getElementById("productRating");
const productPrice = document.getElementById("productPrice");
const productDescription = document.getElementById("productDescription");

const quantityElement = document.getElementById("quantity");
const totalPriceElement = document.getElementById("totalPrice");

const minusBtn = document.getElementById("minusBtn");
const plusBtn = document.getElementById("plusBtn");

const addToCartBtn = document.getElementById("addToCartBtn");
const cartBadge = document.getElementById("cartBadge");

/*==========================================
    VARIABLES
==========================================*/

const params = new URLSearchParams(window.location.search);

const productId = Number(params.get("id"));

const product = foods.find(food => food.id === productId);

let quantity = 1;

/*==========================================
    FUNCTIONS
==========================================*/

function loadProduct() {

    if (!product) {

        alert("Produk tidak dijumpai.");

        window.location.href = "menu.html";

        return;

    }

    productImage.src = product.image;
    productImage.alt = product.name;

    productCategory.textContent = product.category;

    productName.textContent = product.name;

    productRating.textContent = product.rating;

    productPrice.textContent =
        `RM ${product.price.toFixed(2)}`;

    productDescription.textContent =
        product.description;

    updateTotal();

}

function updateTotal() {

    quantityElement.textContent = quantity;

    const total = product.price * quantity;

    totalPriceElement.textContent =
        `RM ${total.toFixed(2)}`;

}

function increaseQuantity() {

    quantity++;

    updateTotal();

}

function decreaseQuantity() {

    if (quantity > 1) {

        quantity--;

        updateTotal();

    }

}

function addToCart() {

    let cart = JSON.parse(localStorage.getItem("cart")) || [];

    const existingItem = cart.find(item => item.id === product.id);

    if (existingItem) {

        existingItem.quantity += quantity;

    } else {

        cart.push({

            id: product.id,

            quantity: quantity

        });

    }

    localStorage.setItem("cart", JSON.stringify(cart));

    updateCartBadge();

    addToCartBtn.innerHTML = `
        <i class="bi bi-check-circle-fill"></i>
        Ditambah
    `;

    setTimeout(() => {

        addToCartBtn.innerHTML = `
            <i class="bi bi-cart-plus"></i>
            Tambah Ke Bakul
        `;

    }, 1500);

}

function updateCartBadge() {

    const cart = JSON.parse(localStorage.getItem("cart")) || [];

    let totalItem = 0;

    cart.forEach(item => {

        totalItem += item.quantity;

    });

    cartBadge.textContent = totalItem;

}

/*==========================================
    EVENT LISTENERS
==========================================*/

plusBtn.addEventListener("click", increaseQuantity);

minusBtn.addEventListener("click", decreaseQuantity);

addToCartBtn.addEventListener("click", addToCart);

/*==========================================
    INITIALIZE
==========================================*/

loadProduct();

updateCartBadge();