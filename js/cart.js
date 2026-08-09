/*==========================================
    CART PAGE
==========================================*/


/*==========================================
    DOM ELEMENTS
==========================================*/

const cartContainer =
    document.getElementById("cartContainer");

const cartSummary =
    document.getElementById("cartSummary");

const totalItemsElement =
    document.getElementById("totalItems");

const cartTotalElement =
    document.getElementById("cartTotal");

const checkoutBtn =
    document.getElementById("checkoutBtn");


/*==========================================
    GET CART
==========================================*/

function getCart() {

    return JSON.parse(
        localStorage.getItem("cart")
    ) || [];

}


/*==========================================
    SAVE CART
==========================================*/

function saveCart(cart) {

    localStorage.setItem(
        "cart",
        JSON.stringify(cart)
    );

}


/*==========================================
    RENDER CART
==========================================*/

function renderCart() {

    const cart = getCart();

    cartContainer.innerHTML = "";


    /*------------------------------------------
        EMPTY CART
    ------------------------------------------*/

    if (cart.length === 0) {

        cartContainer.innerHTML = `

            <div class="empty-cart">

                <i class="bi bi-cart-x"></i>

                <h2>Bakul Anda Kosong</h2>

                <p>
                    Anda belum menambah sebarang makanan.
                </p>

                <a
                    href="menu.html"
                    class="btn btn-primary">

                    Lihat Menu

                </a>

            </div>

        `;

        cartSummary.style.display = "none";

        return;

    }


    cartSummary.style.display = "block";


    /*------------------------------------------
        DISPLAY ITEMS
    ------------------------------------------*/

    cart.forEach(item => {

        cartContainer.innerHTML += `

            <article
                class="cart-item"
                data-id="${item.id}">

                <div class="cart-item-image">

                    <img
                        src="${item.image}"
                        alt="${item.name}">

                </div>


                <div class="cart-item-info">

                    <h3 class="cart-item-name">

                        ${item.name}

                    </h3>

                    <p class="cart-item-price">

                        RM ${item.price.toFixed(2)}

                    </p>


                    <div class="cart-quantity">

                        <button
                            class="quantity-minus"
                            type="button">

                            <i class="bi bi-dash"></i>

                        </button>

                        <span>

                            ${item.quantity}

                        </span>

                        <button
                            class="quantity-plus"
                            type="button">

                            <i class="bi bi-plus"></i>

                        </button>

                    </div>

                </div>


                <button
                    class="remove-cart-btn"
                    type="button"
                    title="Buang item">

                    <i class="bi bi-trash3"></i>

                </button>

            </article>

        `;

    });


    updateSummary();

}


/*==========================================
    UPDATE SUMMARY
==========================================*/

function updateSummary() {

    const cart = getCart();

    let totalItems = 0;

    let totalPrice = 0;


    cart.forEach(item => {

        totalItems += item.quantity;

        totalPrice +=
            item.price * item.quantity;

    });


    totalItemsElement.textContent =
        totalItems;

    cartTotalElement.textContent =
        `RM ${totalPrice.toFixed(2)}`;

}


/*==========================================
    CHANGE QUANTITY
==========================================*/

function changeQuantity(id, amount) {

    const cart = getCart();

    const item =
        cart.find(item => item.id === id);


    if (!item) return;


    item.quantity += amount;


    if (item.quantity <= 0) {

        const updatedCart =
            cart.filter(item => item.id !== id);

        saveCart(updatedCart);

    }

    else {

        saveCart(cart);

    }


    renderCart();

}


/*==========================================
    REMOVE ITEM
==========================================*/

function removeItem(id) {

    const cart = getCart();

    const updatedCart =
        cart.filter(item => item.id !== id);

    saveCart(updatedCart);

    renderCart();

}


/*==========================================
    CART EVENTS
==========================================*/

cartContainer.addEventListener(
    "click",
    event => {

        const cartItem =
            event.target.closest(".cart-item");

        if (!cartItem) return;


        const id =
            Number(cartItem.dataset.id);


        /*--------------------------------------
            PLUS
        --------------------------------------*/

        if (
            event.target.closest(".quantity-plus")
        ) {

            changeQuantity(id, 1);

        }


        /*--------------------------------------
            MINUS
        --------------------------------------*/

        if (
            event.target.closest(".quantity-minus")
        ) {

            changeQuantity(id, -1);

        }


        /*--------------------------------------
            REMOVE
        --------------------------------------*/

        if (
            event.target.closest(".remove-cart-btn")
        ) {

            removeItem(id);

        }

    }
);


/*==========================================
    CHECKOUT
==========================================*/

checkoutBtn.addEventListener(
    "click",
    () => {

        const cart = getCart();

        if (cart.length === 0) {

            return;

        }

        window.location.href =
            "checkout.html";

    }
);


/*==========================================
    INITIALIZE
==========================================*/

renderCart();