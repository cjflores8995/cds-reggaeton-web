# Cambio visual - Tienda CDs Reggaeton

Archivos incluidos:

1. `index.php` — reemplaza el `index.php` actual.
2. `product.php` — archivo nuevo para la ficha de cada CD.
3. `store.css` — archivo nuevo para la interfaz pública.
4. `store.js` — archivo nuevo para búsqueda, filtros, carrito y WhatsApp.

Copia los cuatro archivos directamente en:

`C:\laragon\www\TiendaCDsReggaeton\`

No reemplaces `style.php`: el panel `admin.php` todavía lo utiliza.

Después abre:

`http://localhost/TiendaCDsReggaeton/`

Haz `Ctrl + F5` para evitar que el navegador conserve CSS/JS anterior.

La nueva tienda no usa categorías, no usa selector de cantidades y no llama a `showCatName()`. Cada CD se agrega una sola vez al carrito.
