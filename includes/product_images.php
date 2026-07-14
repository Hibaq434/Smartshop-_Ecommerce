<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Product Image Configuration
|--------------------------------------------------------------------------
*/

const PRODUCT_IMAGE_DIR      = __DIR__ . '/../assets/images/products/';
const PRODUCT_IMAGE_WEB_PATH = 'assets/images/products/';
const PRODUCT_IMAGE_DEFAULT_FILE = 'default.jpg';
const PRODUCT_IMAGE_DEFAULT  = PRODUCT_IMAGE_WEB_PATH . PRODUCT_IMAGE_DEFAULT_FILE;

/*
|--------------------------------------------------------------------------
| Ensure products.image column exists
|--------------------------------------------------------------------------
*/

function ensureProductsImageColumn(mysqli $conn): void
{
    static $checked = false;

    if ($checked) {
        return;
    }

    $checked = true;

    $result = mysqli_query($conn, "SHOW COLUMNS FROM products LIKE 'image'");

    if ($result && mysqli_num_rows($result) === 0) {
        mysqli_query(
            $conn,
            "ALTER TABLE products
             ADD COLUMN image VARCHAR(255) DEFAULT NULL
             AFTER quantity"
        );
    }
}

/*
|--------------------------------------------------------------------------
| Available Product Images
| Keys are the EXACT filenames as they exist on disk and in the database.
|--------------------------------------------------------------------------
*/

function productImages(): array
{
    return [
        'default.jpg'                       => 'Default Image',
        'Smart Watch.jpg'                   => 'Smart Watch',
        'Headphones.jpg'                    => 'Headphones',
        'Wireless Headphones.jpg'           => 'Wireless Headphones',
        'Portable Speaker.jpg'              => 'Portable Speaker',
        'DSLR Camera.jpg'                   => 'DSLR Camera',
        'Gaming Laptop.jpg'                 => 'Gaming Laptop',
        'Smartphone.jpg'                    => 'Smartphone',
        'Tablet.jpg'                        => 'Tablet',
        'Keyboard.jpg'                      => 'Keyboard',
        'Mouse.jpg'                         => 'Mouse',
        'Additional Electronics.jpg'        => 'Additional Electronics',
        'Complete Technology Collection.jpg' => 'Complete Technology Collection',
    ];
}

/*
|--------------------------------------------------------------------------
| Filename List
|--------------------------------------------------------------------------
*/

function productImageFilenames(): array
{
    return array_keys(productImages());
}

/*
|--------------------------------------------------------------------------
| Normalize Image Filename
| Strips any directory path a caller may have accidentally stored.
| Does NOT alter the filename itself — spaces are preserved.
|--------------------------------------------------------------------------
*/

function normalizeProductImage(?string $image): string
{
    $image = trim((string)$image);

    if ($image === '') {
        return '';
    }

    // Remove any directory component — keep only the bare filename
    $image = basename(str_replace('\\', '/', $image));

    return $image;
}

/*
|--------------------------------------------------------------------------
| Resolve Image Filename
|
| Priority order:
|   1. Exact match against the whitelist (fastest, most common case).
|   2. Case-insensitive match against the whitelist (handles DB case drift).
|   3. Physical file exists on disk even if not in the whitelist
|      (handles images added to disk without updating productImages()).
|   4. Fall back to default.jpg.
|--------------------------------------------------------------------------
*/

function resolveProductImageFilename(?string $image): string
{
    $filename = normalizeProductImage($image);

    if ($filename === '') {
        return PRODUCT_IMAGE_DEFAULT_FILE;
    }

    // 1. Exact whitelist match
    if (in_array($filename, productImageFilenames(), true)) {
        return $filename;
    }

    // 2. Case-insensitive whitelist match
    $filenameLower = mb_strtolower($filename);
    foreach (productImageFilenames() as $known) {
        if (mb_strtolower($known) === $filenameLower) {
            return $known;
        }
    }

    // 3. File physically exists on disk (not in whitelist but still valid)
    $absolutePath = PRODUCT_IMAGE_DIR . $filename;
    if (is_file($absolutePath)) {
        return $filename;
    }

    return PRODUCT_IMAGE_DEFAULT_FILE;
}

/*
|--------------------------------------------------------------------------
| Get Product Image Web URL
|
| Returns a web-root-relative URL ready to use in an <img src="...">.
| Spaces in filenames are percent-encoded so the URL is always valid.
|
| Example outputs:
|   'Smart Watch.jpg'  ->  'assets/images/products/Smart%20Watch.jpg'
|   'default.jpg'      ->  'assets/images/products/default.jpg'
|--------------------------------------------------------------------------
*/

function productImageUrl(?string $image): string
{
    $trimmed = trim((string)$image);

    // Online image — products.image already holds a full URL, so use it
    // directly. No DB/schema change needed; the existing VARCHAR column
    // already stores whatever string was written into it.
    if ($trimmed !== '' && preg_match('#^https?://#i', $trimmed) === 1) {
        return $trimmed;
    }

    // Otherwise fall back to the original local-file behavior, unchanged.
    $filename = resolveProductImageFilename($image);

    $absolutePath = PRODUCT_IMAGE_DIR . $filename;

    if (!is_file($absolutePath)) {
        // Default must exist — if not, return path anyway so Apache gives a
        // proper 404 rather than PHP throwing a fatal error
        return PRODUCT_IMAGE_WEB_PATH . PRODUCT_IMAGE_DEFAULT_FILE;
    }

    // Percent-encode only the filename portion so spaces become %20.
    // rawurlencode() is correct here (RFC 3986); urlencode() would turn
    // spaces into + signs which browsers do not expand in src attributes.
    $encodedFilename = rawurlencode($filename);

    return PRODUCT_IMAGE_WEB_PATH . $encodedFilename;
}

/*
|--------------------------------------------------------------------------
| Admin Image Dropdown
|--------------------------------------------------------------------------
*/

function productImageDropdown(
    ?string $selected,
    string $fieldName  = 'product_image',
    string $selectClass = ''
): string {

    $selected = resolveProductImageFilename($selected);

    $classAttribute = '';

    if ($selectClass !== '') {
        $classAttribute = ' class="' .
            htmlspecialchars($selectClass, ENT_QUOTES, 'UTF-8') .
            '"';
    }

    $html = '<select name="' .
        htmlspecialchars($fieldName, ENT_QUOTES, 'UTF-8') .
        '"' .
        $classAttribute .
        '>';

    foreach (productImages() as $filename => $label) {

        $isSelected = ($filename === $selected)
            ? ' selected'
            : '';

        $html .= '<option value="' .
            htmlspecialchars($filename, ENT_QUOTES, 'UTF-8') .
            '"' .
            $isSelected .
            '>' .
            htmlspecialchars($label, ENT_QUOTES, 'UTF-8') .
            '</option>';
    }

    $html .= '</select>';

    return $html;
}