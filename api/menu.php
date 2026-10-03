<?php

header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../config.php';

    $categoriesResult = $conn->query(
        'SELECT category_id, category_name
         FROM categories
         WHERE is_active = 1
         ORDER BY category_id'
    );

    $categories = [];
    while ($category = $categoriesResult->fetch_assoc()) {
        $categories[] = [
            'id' => (int) $category['category_id'],
            'name' => $category['category_name'],
        ];
    }

    $itemsResult = $conn->query(
        'SELECT menu_items.item_id, menu_items.item_name, menu_items.description,
                menu_items.price, menu_items.image_path, categories.category_name
         FROM menu_items
         INNER JOIN categories ON categories.category_id = menu_items.category_id
         WHERE menu_items.is_available = 1 AND categories.is_active = 1
         ORDER BY menu_items.item_id'
    );

    $items = [];
    while ($item = $itemsResult->fetch_assoc()) {
        $items[] = [
            'id' => (int) $item['item_id'],
            'name' => $item['item_name'],
            'description' => $item['description'] ?? '',
            'price' => (float) $item['price'],
            'image' => $item['image_path'] ?: 'images/foods/category/makanan.png',
            'category' => $item['category_name'],
        ];
    }

    echo json_encode(
        ['categories' => $categories, 'items' => $items],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
} catch (Throwable $exception) {
    error_log('Menu API error: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(
        ['error' => 'Menu tidak dapat dimuat. Sila semak sambungan pangkalan data.'],
        JSON_UNESCAPED_UNICODE
    );
}
