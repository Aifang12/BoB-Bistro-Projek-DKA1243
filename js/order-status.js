/*==========================================
    ORDER STATUS PAGE
==========================================*/


/*==========================================
    DOM ELEMENTS
==========================================*/

const orderIdElement =
    document.getElementById("orderId");

const orderStatusElement =
    document.getElementById("orderStatus");

const orderTableElement =
    document.getElementById("orderTable");

const orderPaymentElement =
    document.getElementById("orderPayment");

const orderNotesElement =
    document.getElementById("orderNotes");

const orderItemsElement =
    document.getElementById("orderItems");

const orderItemsContainer =
    document.getElementById(
        "orderItemsContainer"
    );

const orderTotalElement =
    document.getElementById("orderTotal");


/*==========================================
    GET ORDER
==========================================*/

function getOrder() {

    return JSON.parse(
        localStorage.getItem("currentOrder")
    );

}


/*==========================================
    PAYMENT LABEL
==========================================*/

function getPaymentLabel(paymentMethod) {

    switch (paymentMethod) {

        case "fpx":

            return "FPX";

        case "online-banking":

            return "Online Banking";

        case "cash":

            return "Tunai di Kaunter";

        default:

            return "Tidak Dinyatakan";

    }

}


/*==========================================
    LOAD ORDER
==========================================*/

function loadOrder() {

    const order = getOrder();


    /*------------------------------------------
        ORDER NOT FOUND
    ------------------------------------------*/

    if (!order) {

        orderIdElement.textContent =
            "Tiada Tempahan";

        orderStatusElement.textContent =
            "-";

        orderTableElement.textContent =
            "-";

        orderPaymentElement.textContent =
            "-";

        orderNotesElement.textContent =
            "-";

        orderItemsElement.textContent =
            "0";

        orderTotalElement.textContent =
            "RM 0.00";

        orderItemsContainer.innerHTML = `

            <div class="empty-cart">

                <i class="bi bi-receipt"></i>

                <h2>
                    Tiada tempahan ditemui
                </h2>

                <p>
                    Sila buat tempahan terlebih dahulu.
                </p>

                <a
                    href="menu.html"
                    class="btn btn-primary">

                    Lihat Menu

                </a>

            </div>

        `;

        return;

    }


    /*------------------------------------------
        ORDER INFORMATION
    ------------------------------------------*/

    orderIdElement.textContent =
        order.id;

    orderStatusElement.textContent =
        order.status;

    orderTableElement.textContent =
        `Meja ${order.table}`;

    orderPaymentElement.textContent =
        getPaymentLabel(
            order.paymentMethod
        );

    orderNotesElement.textContent =
        order.notes || "Tiada komen";

    orderItemsElement.textContent =
        order.totalItems;

    orderTotalElement.textContent =
        `RM ${order.totalPrice.toFixed(2)}`;


    /*------------------------------------------
        DISPLAY ITEMS
    ------------------------------------------*/

    orderItemsContainer.innerHTML = "";


    order.items.forEach(item => {

        const itemTotal =
            item.price * item.quantity;


        orderItemsContainer.innerHTML += `

            <article class="order-item">

                <div class="order-item-image">

                    <img
                        src="${item.image}"
                        alt="${item.name}">

                </div>


                <div class="order-item-info">

                    <h3 class="order-item-name">

                        ${item.name}

                    </h3>

                    <span class="order-item-quantity">

                        Kuantiti: ${item.quantity}

                    </span>

                </div>


                <span class="order-item-price">

                    RM ${itemTotal.toFixed(2)}

                </span>

            </article>

        `;

    });

}


/*==========================================
    INITIALIZE
==========================================*/

loadOrder();