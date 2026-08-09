/*==========================================
    CHECKOUT PAGE
==========================================*/


/*==========================================
    DOM ELEMENTS
==========================================*/

const checkoutItems =
    document.getElementById("checkoutItems");

const checkoutItemCount =
    document.getElementById("checkoutItemCount");

const checkoutTotal =
    document.getElementById("checkoutTotal");

const orderNotes =
    document.getElementById("orderNotes");

const placeOrderBtn =
    document.getElementById("placeOrderBtn");

const checkoutTable =
    document.getElementById("checkoutTable");


/*==========================================
    GET ACTIVE TABLE
==========================================*/

function getActiveTable() {

    return localStorage.getItem(
        "activeTable"
    );

}


/*==========================================
    CHECK TABLE SESSION
==========================================*/

function checkTableSession() {

    const activeTable =
        getActiveTable();


    if (!activeTable) {

        alert(
            "Sila imbas QR Code meja terlebih dahulu."
        );

        window.location.href =
            "index.html";

        return false;

    }


    return true;

}


/*==========================================
    LOAD ACTIVE TABLE
==========================================*/

function loadActiveTable() {

    const activeTable =
        getActiveTable();


    if (!activeTable) {

        checkoutTable.textContent =
            "Meja tidak dikenal pasti";

        return;

    }


    checkoutTable.textContent =
        `Meja ${activeTable}`;

}


/*==========================================
    GET CART
==========================================*/

function getCart() {

    return JSON.parse(
        localStorage.getItem("cart")
    ) || [];

}


/*==========================================
    LOAD CHECKOUT
==========================================*/

function loadCheckout() {

    const cart = getCart();

    checkoutItems.innerHTML = "";


    /*------------------------------------------
        EMPTY CART
    ------------------------------------------*/

    if (cart.length === 0) {

        checkoutItems.innerHTML = `

            <div class="empty-cart">

                <i class="bi bi-cart-x"></i>

                <h2>
                    Bakul Anda Kosong
                </h2>

                <p>
                    Tiada item untuk membuat tempahan.
                </p>

                <a
                    href="menu.html"
                    class="btn btn-primary">

                    Kembali ke Menu

                </a>

            </div>

        `;

        placeOrderBtn.disabled = true;

        return;

    }


    /*------------------------------------------
        DISPLAY ITEMS
    ------------------------------------------*/

    let totalItems = 0;

    let totalPrice = 0;


    cart.forEach(item => {

        const itemTotal =
            item.price * item.quantity;


        totalItems +=
            item.quantity;


        totalPrice +=
            itemTotal;


        checkoutItems.innerHTML += `

            <article class="checkout-item">

                <div class="checkout-item-image">

                    <img
                        src="${item.image}"
                        alt="${item.name}">

                </div>


                <div class="checkout-item-info">

                    <h3 class="checkout-item-name">

                        ${item.name}

                    </h3>


                    <span class="checkout-item-quantity">

                        Kuantiti: ${item.quantity}

                    </span>

                </div>


                <span class="checkout-item-total">

                    RM ${itemTotal.toFixed(2)}

                </span>

            </article>

        `;

    });


    /*------------------------------------------
        UPDATE SUMMARY
    ------------------------------------------*/

    checkoutItemCount.textContent =
        totalItems;


    checkoutTotal.textContent =
        `RM ${totalPrice.toFixed(2)}`;

}


/*==========================================
    GET PAYMENT METHOD
==========================================*/

function getPaymentMethod() {

    const selected =
        document.querySelector(
            'input[name="paymentMethod"]:checked'
        );


    return selected
        ? selected.value
        : null;

}


/*==========================================
    GENERATE ORDER ID
==========================================*/

function generateOrderId() {

    const randomNumber =
        Math.floor(
            1000 + Math.random() * 9000
        );


    return `BAB-${randomNumber}`;

}


/*==========================================
    PLACE ORDER
==========================================*/

placeOrderBtn.addEventListener(
    "click",
    () => {

        /*--------------------------------------
            CHECK TABLE SESSION
        --------------------------------------*/

        const activeTable =
            getActiveTable();


        if (!activeTable) {

            alert(
                "Sesi meja tidak ditemui. Sila imbas QR Code meja terlebih dahulu."
            );

            window.location.href =
                "index.html";

            return;

        }


        /*--------------------------------------
            CHECK CART
        --------------------------------------*/

        const cart =
            getCart();


        if (cart.length === 0) {

            alert(
                "Bakul anda kosong."
            );

            return;

        }


        /*--------------------------------------
            PAYMENT
        --------------------------------------*/

        const paymentMethod =
            getPaymentMethod();


        if (!paymentMethod) {

            alert(
                "Sila pilih kaedah pembayaran."
            );

            return;

        }


        /*--------------------------------------
            CALCULATE TOTAL
        --------------------------------------*/

        let totalPrice = 0;

        let totalItems = 0;


        cart.forEach(item => {

            totalPrice +=
                item.price * item.quantity;


            totalItems +=
                item.quantity;

        });


        /*--------------------------------------
            CREATE ORDER
        --------------------------------------*/

        const createdAt =
            new Date().toISOString();

        const order = {

            id:
                generateOrderId(),

            table:
                Number(activeTable),

            items:
                cart,

            totalItems:
                totalItems,

            totalPrice:
                totalPrice,

            paymentMethod:
                paymentMethod,

            notes:
                orderNotes
                    ? orderNotes.value.trim()
                    : "",

            status:
                "Menunggu",

            createdAt:
                createdAt

        };


        /*--------------------------------------
            SAVE ORDER
        --------------------------------------*/

        localStorage.setItem(
            "currentOrder",
            JSON.stringify(order)
        );

        localStorage.setItem(
            "orderStatusStartTime",
            String(new Date(createdAt).getTime())
        );


        /*--------------------------------------
            CLEAR CART
        --------------------------------------*/

        localStorage.removeItem(
            "cart"
        );


        /*--------------------------------------
            GO TO ORDER STATUS
        --------------------------------------*/

        window.location.href =
            "order-status.html";

    }
);


/*==========================================
    INITIALIZE
==========================================*/

if (checkTableSession()) {

    loadActiveTable();

    loadCheckout();

}