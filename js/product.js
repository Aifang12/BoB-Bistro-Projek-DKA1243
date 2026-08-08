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
    document.querySelector(".cart-badge");


/*==========================================
    QUANTITY
==========================================*/

let quantity = 1;


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

    totalPriceElement.textContent =
        `RM ${(product.price * quantity).toFixed(2)}`;

}


/*==========================================
    FIND PRODUCT
==========================================*/

const product =
    foods.find(food => food.id === productId);


/*==========================================
    PRODUCT NOT FOUND
==========================================*/

if (!product) {

    productName.textContent =
        "Produk tidak dijumpai.";

    productDescription.textContent =
        "Maaf, produk yang anda cari tidak tersedia.";

    addToCartBtn.disabled = true;

    updateTotalPrice();

}


/*==========================================
    DISPLAY PRODUCT
==========================================*/

else {

    productImage.src =
        product.image;

    productImage.alt =
        product.name;

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
        UPDATE BADGE
    ------------------------------------------*/

    updateCartBadge();


    /*------------------------------------------
        RESET QUANTITY
    ------------------------------------------*/

    quantity = 1;

    quantityElement.textContent =
        quantity;

    updateTotalPrice();


    /*------------------------------------------
        BUTTON FEEDBACK
    ------------------------------------------*/

    const originalText =
        addToCartBtn.textContent;

    addToCartBtn.textContent =
        "Ditambah ke Bakul ✓";


    setTimeout(() => {

        addToCartBtn.textContent =
            originalText;

    }, 1500);

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

updateCartBadge();
updateTotalPrice();