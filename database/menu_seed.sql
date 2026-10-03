USE `bistro_db`;

INSERT INTO `menu_items`
    (`item_id`, `category_id`, `item_name`, `description`, `price`, `image_path`, `is_available`)
VALUES
    (1, 1, 'Nasi Ayam', 'Ayam goreng rangup dihidangkan bersama nasi putih, sambal dan timun segar.', 12.00, 'images/foods/set/Nasi Ayam.png', 1),
    (2, 1, 'Nasi Lemak Ayam Goreng Berempah', 'Nasi lemak beras basmati beraroma santan dan daun pandan, dihidangkan bersama sambal tumis pedas manis, ayam goreng berempah, telur rebus, kacang, dan ikan bilis.', 22.00, 'images/foods/set/Nasi Lemak Ayam Goreng Berempah.png', 1),
    (3, 1, 'Nasi Daging Harimau Menangis', 'Nasi putih lembut dihidangkan bersama daging lembu bakar empuk, air asam utara yang padu, ulam-ulaman segar, dan sup kosong.', 28.00, 'images/foods/set/Nasi Daging Harimau Menangis.png', 1),
    (4, 1, 'Chicken Chop Crispy Sos Lada Hitam', 'Kepingan paha ayam digoreng rangup, disiram sos lada hitam pekat, dihidangkan bersama kentang goreng dan coleslaw.', 24.00, 'images/foods/set/Chicken Chop Crispy Sos Lada Hitam.png', 1),
    (5, 4, 'Iced Sparkling Berry Lemonade', 'Minuman soda berkarbonat segar digabungkan dengan pati beri dan perahan jus lemon segar.', 16.00, 'images/foods/drinks/Iced Sparkling Berry Lemonade.png', 1),
    (6, 4, 'Matcha Espresso Latte', 'Gabungan matcha Jepun premium, susu segar, dan satu shot kopi espresso.', 18.00, 'images/foods/drinks/Matcha Espresso Latte.png', 1),
    (7, 4, 'Classic Iced Peach Tea', 'Teh ais segar dengan rasa buah pic manis dan hirisan buah segar.', 14.00, 'images/foods/drinks/Classic Iced Peach Tea.png', 1),
    (8, 4, 'B@B Signature Chocolate', 'Minuman coklat pekat panas atau ais yang dibuat daripada coklat artisanal premium.', 16.00, 'images/foods/drinks/B@B Signature Chocolate.png', 1),
    (9, 5, 'Classic Crème Brûlée', 'Kastard vanila lembut dengan lapisan gula karamel rangup yang dibakar di bahagian atas.', 24.00, 'images/foods/desserts/Classic Crème Brûlée.png', 1),
    (10, 5, 'Warm Apple Tarte Tatin', 'Pai epal karamel gaya Perancis dengan pastri rangup, dihidangkan bersama gelato vanila.', 26.00, 'images/foods/desserts/Warm Apple Tarte Tatin.png', 1),
    (11, 5, 'Dark Chocolate Mousse', 'Mousse coklat gelap 70 peratus yang kaya dan gebu, ditaburi garam laut dan krim chantilly.', 22.00, 'images/foods/desserts/Dark Chocolate Mousse.png', 1),
    (12, 5, 'Churros Sos Coklat', 'Churros goreng panas dan rangup ditabur gula kayu manis, dihidangkan bersama sos celupan coklat pekat.', 18.00, 'images/foods/desserts/Churros Sos Coklat.png', 1),
    (13, 6, 'French Onion Soup', 'Sup bawang Perancis kaya rasa, dihidangkan bersama roti bakar dan keju Gruyère leleh.', 14.00, 'images/foods/snack/French Onion Soup.png', 1),
    (14, 6, 'Whipped Ricotta Sourdough', 'Keju ricotta gebu disiram madu dan minyak zaitun, dihidangkan bersama roti sourdough bakar.', 32.00, 'images/foods/snack/Whipped Ricotta Sourdough.png', 1),
    (15, 6, 'Escargots à la Bourguignonne', 'Siput escargot dimasak dalam mentega bawang putih dan herba segar, dihidangkan bersama roti Perancis bakar.', 38.00, 'images/foods/snack/Escargots à la Bourguignonne.png', 1),
    (16, 6, 'Crispy Truffle Fries', 'Kentang goreng rangup ditaburi minyak truffle, keju parmesan, dan herba parsley.', 22.00, 'images/foods/snack/Crispy Truffle Fries.png', 1)
ON DUPLICATE KEY UPDATE
    `category_id` = VALUES(`category_id`),
    `item_name` = VALUES(`item_name`),
    `description` = VALUES(`description`),
    `price` = VALUES(`price`),
    `image_path` = VALUES(`image_path`),
    `is_available` = VALUES(`is_available`);
