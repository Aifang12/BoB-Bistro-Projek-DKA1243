/*==========================================
    ORDER STATUS PAGE
==========================================*/


/*==========================================
    DOM ELEMENTS
==========================================*/

const orderIdElement =
    document.getElementById("orderId");

const orderTableElement =
    document.getElementById("orderTable");

const orderPaymentElement =
    document.getElementById("orderPayment");

const orderNotesElement =
    document.getElementById("orderNotes");

const orderTotalElement =
    document.getElementById("orderTotal");

const orderItemsElement =
    document.getElementById("orderItems");

const statusIcon =
    document.getElementById("statusIcon");

const statusTitle =
    document.getElementById("statusTitle");

const statusDescription =
    document.getElementById("statusDescription");

const cartBadge =
    document.getElementById("cartBadge");


/*==========================================
    GET ORDER
==========================================*/

const currentOrder =
    JSON.parse(
        localStorage.getItem("currentOrder")
    );


/*==========================================
    STATUS DATA
==========================================*/

const orderStatuses = [

    {
        name: "Menunggu",

        description:
            "Pesanan anda telah diterima.",

        icon:
            "bi-clock"
    },

    {
        name: "Sedang Disediakan",

        description:
            "Pesanan sedang disediakan.",

        icon:
            "bi-fire"
    },

    {
        name: "Sedia Diambil",

        description:
            "Pesanan anda sudah siap.",

        icon:
            "bi-check-circle"
    }

];


/*==========================================
    STATUS START TIME
==========================================*/

function getStatusStartTime() {

    const orderCreatedAt =
        currentOrder?.createdAt;

    if (orderCreatedAt) {

        const createdAtTime =
            new Date(orderCreatedAt).getTime();

        localStorage.setItem(
            "orderStatusStartTime",
            String(createdAtTime)
        );

        return createdAtTime;

    }


    let startTime =
        localStorage.getItem(
            "orderStatusStartTime"
        );


    if (!startTime) {

        startTime =
            Date.now();

        localStorage.setItem(
            "orderStatusStartTime",
            startTime
        );

    }


    return Number(startTime);

}


/*==========================================
    GET CURRENT STATUS
==========================================*/

function getCurrentStatus() {

    const startTime =
        getStatusStartTime();

    const elapsed =
        Math.floor(
            (Date.now() - startTime) / 1000
        );


    /*
        0 - 9 saat
        = Menunggu

        10 - 19 saat
        = Sedang Disediakan

        20 saat ke atas
        = Sedia Diambil
    */


    if (elapsed < 10) {

        return 0;

    }


    if (elapsed < 20) {

        return 1;

    }


    return 2;

}


/*==========================================
    DISPLAY STATUS
==========================================*/

function displayStatus() {

    const statusIndex =
        getCurrentStatus();

    const status =
        orderStatuses[statusIndex];


    /*--------------------------------------
        ICON
    --------------------------------------*/

    statusIcon.innerHTML = `

        <i class="bi ${status.icon}"></i>

    `;


    /*--------------------------------------
        TITLE
    --------------------------------------*/

    statusTitle.textContent =
        status.name;


    /*--------------------------------------
        DESCRIPTION
    --------------------------------------*/

    statusDescription.textContent =
        status.description;


    /*--------------------------------------
        SAVE STATUS
    --------------------------------------*/

    if (currentOrder) {

        currentOrder.status =
            status.name;

        localStorage.setItem(
            "currentOrder",
            JSON.stringify(currentOrder)
        );

    }

}


/*==========================================
    LOAD ORDER INFORMATION
==========================================*/

function loadOrderInformation() {

    if (!currentOrder) {

        orderIdElement.textContent =
            "Tiada Tempahan";

        orderTableElement.textContent =
            "-";

        orderPaymentElement.textContent =
            "-";

        orderNotesElement.textContent =
            "-";

        orderTotalElement.textContent =
            "RM 0.00";

        return;

    }


    /*--------------------------------------
        ORDER ID
    --------------------------------------*/

    orderIdElement.textContent =
        currentOrder.id;


    /*--------------------------------------
        TABLE
    --------------------------------------*/

    orderTableElement.textContent =
        `Meja ${currentOrder.table}`;


    /*--------------------------------------
        PAYMENT
    --------------------------------------*/

    orderPaymentElement.textContent =
        formatPaymentMethod(
            currentOrder.paymentMethod,
            currentOrder.paymentProvider
        );


    /*--------------------------------------
        NOTES
    --------------------------------------*/

    orderNotesElement.textContent =
        currentOrder.notes
        ? currentOrder.notes
        : "Tiada komen";


    /*--------------------------------------
        TOTAL
    --------------------------------------*/

    orderTotalElement.textContent =
        `RM ${currentOrder.totalPrice.toFixed(2)}`;

}


/*==========================================
    FORMAT PAYMENT METHOD
==========================================*/

function formatEWalletProvider(provider) {

    switch (provider) {

        case "touchngo":

            return "Touch N Go";


        case "googlepay":

            return "Google Pay";


        case "applepay":

            return "Apple Pay";


        case "boost":

            return "Boost";


        default:

            return "E-Wallet";

    }

}


function formatPaymentMethod(method, provider) {

    switch (method) {

        case "e-wallet":

            return `E-Wallet (${formatEWalletProvider(provider)})`;


        case "fpx":

            return "FPX";


        case "online-banking":

        case "online":

            return "Online Banking";


        case "cash":

            return "Tunai";


        default:

            return method || "-";

    }

}


/*==========================================
    LOAD ORDER ITEMS
==========================================*/

function loadOrderItems() {

    if (!currentOrder) {

        orderItemsElement.innerHTML = `

            <p class="no-result">
                Tiada maklumat pesanan.
            </p>

        `;

        return;

    }


    orderItemsElement.innerHTML = "";


    currentOrder.items.forEach(item => {

        const itemTotal =
            item.price * item.quantity;


        orderItemsElement.innerHTML += `

            <div class="summary-item">

                <div class="summary-image">

                    <img
                        src="${item.image}"
                        alt="${item.name}">

                </div>


                <div class="summary-info">

                    <h3>
                        ${item.name}
                    </h3>

                    <span>
                        Kuantiti: ${item.quantity}
                    </span>

                </div>


                <span class="summary-price">

                    RM ${itemTotal.toFixed(2)}

                </span>

            </div>

        `;

    });

}


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

        totalItems +=
            item.quantity;

    });


    cartBadge.textContent =
        totalItems;

}


/*==========================================
    CHECK ORDER
==========================================*/

function checkOrderStatus() {

    if (!currentOrder) return;


    displayStatus();

}


/*==========================================
    INITIALIZE
==========================================*/

loadOrderInformation();

loadOrderItems();

updateCartBadge();

checkOrderStatus();


/*==========================================
    REAL-TIME STATUS UPDATE
==========================================*/

setInterval(() => {

    checkOrderStatus();

}, 1000);