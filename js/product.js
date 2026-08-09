/*==========================================
    PRODUCT PAGE
==========================================*/


/*==========================================
    GET PRODUCT ID
==========================================*/

const urlParams =
    new URLSearchParams(window.location.search);

const productId =
    Number(urlParams.get("id"));


/*==========================================
    DOM ELEMENTS
==========================================*/

const productImage =
    document.getElementById("productImage");

const productCategory =
    document.getElementById("productCategory");

const productName =
    document.getElementById("productName");

const productRating =
    document.getElementById("productRating");

const productPrice =
    document.getElementById("productPrice");

const productDescription =
    document.getElementById("productDescription");

const totalPriceElement =
    document.getElementById("totalPrice");

const quantityElement =
    document.getElementById("quantity");

const minusBtn =
    document.getElementById("minusBtn");

const plusBtn =
    document.getElementById("plusBtn");

const addToCartBtn =
    document.getElementById("addToCartBtn");

const cartBadge =
    document.getElementById("cartBadge");


/*==========================================
    QUANTITY
==========================================*/

let quantity = 1;


/*==========================================
    FIND PRODUCT
==========================================*/

const product =
    foods.find(food => food.id === productId);


/*==========================================
    UPDATE TOTAL PRICE
==========================================*/

function updateTotalPrice() {

    if (!totalPriceElement) return;

    if (!product) {

        totalPriceElement.textContent =
            "RM 0.00";

        return;

    }

    const total =
        product.price * quantity;

    totalPriceElement.textContent =
        `RM ${total.toFixed(2)}`;

}


/*==========================================
    LOAD PRODUCT
==========================================*/

function loadProduct() {

    if (!product) {

        productName.textContent =
            "Produk tidak dijumpai.";

        productDescription.textContent =
            "Maaf, produk yang anda cari tidak tersedia.";

        if (addToCartBtn) {

            addToCartBtn.disabled = true;

        }

        updateTotalPrice();

        return;

    }


    /*------------------------------------------
        PRODUCT IMAGE
    ------------------------------------------*/

    productImage.src =
        product.image;

    productImage.alt =
        product.name;


    /*------------------------------------------
        PRODUCT INFORMATION
    ------------------------------------------*/

    productCategory.textContent =
        product.category;

    productName.textContent =
        product.name;

    productRating.textContent =
        product.rating;

    productPrice.textContent =
        `RM ${product.price.toFixed(2)}`;

    productDescription.textContent =
        product.description;


    /*------------------------------------------
        INITIAL TOTAL
    ------------------------------------------*/

    quantity = 1;

    quantityElement.textContent =
        quantity;

    updateTotalPrice();

}


/*==========================================
    PLUS BUTTON
==========================================*/

plusBtn.addEventListener("click", () => {

    quantity++;

    quantityElement.textContent =
        quantity;

    updateTotalPrice();

});


/*==========================================
    MINUS BUTTON
==========================================*/

minusBtn.addEventListener("click", () => {

    if (quantity > 1) {

        quantity--;

        quantityElement.textContent =
            quantity;

        updateTotalPrice();

    }

});


/*==========================================
    ADD TO CART
==========================================*/

addToCartBtn.addEventListener("click", () => {

    if (!product) return;


    /*------------------------------------------
        GET EXISTING CART
    ------------------------------------------*/

    let cart =
        JSON.parse(
            localStorage.getItem("cart")
        ) || [];


    /*------------------------------------------
        CHECK EXISTING PRODUCT
    ------------------------------------------*/

    const existingProduct =
        cart.find(
            item => item.id === product.id
        );


    if (existingProduct) {

        existingProduct.quantity += quantity;

    }

    else {

        cart.push({

            id: product.id,

            name: product.name,

            price: product.price,

            image: product.image,

            quantity: quantity

        });

    }


    /*------------------------------------------
        SAVE CART
    ------------------------------------------*/

    localStorage.setItem(
        "cart",
        JSON.stringify(cart)
    );


    /*------------------------------------------
        UPDATE CART BADGE
    ------------------------------------------*/

    updateCartBadge();


    /*------------------------------------------
        BUTTON FEEDBACK
    ------------------------------------------*/

    const originalHTML =
        addToCartBtn.innerHTML;


    addToCartBtn.innerHTML = `
        <i class="bi bi-check-circle-fill"></i>
        Ditambah
    `;


    /*------------------------------------------
        RESET BUTTON
    ------------------------------------------*/

    setTimeout(() => {

        addToCartBtn.innerHTML =
            originalHTML;

    }, 1500);


    /*------------------------------------------
        RESET QUANTITY
    ------------------------------------------*/

    quantity = 1;

    quantityElement.textContent =
        quantity;

    updateTotalPrice();

});


/*==========================================
    CART BADGE
==========================================*/

function updateCartBadge() {

    if (!cartBadge) return;


    const cart =
        JSON.parse(
            localStorage.getItem("cart")
        ) || [];


    let totalItems = 0;


    cart.forEach(item => {

        totalItems += item.quantity;

    });


    cartBadge.textContent =
        totalItems;

}


/*==========================================
    INITIALIZE
==========================================*/

loadProduct();

updateCartBadge();