# JVM Schema

[![WordPress Version](https://img.shields.io/badge/wordpress-%3E%3D5.0-blue.svg)](https://wordpress.org/)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D7.4-8892bf.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-GPL--2.0+-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

**JVM Schema** is a powerful and dynamic structured data manager for WordPress and WooCommerce. It allows you to implement clean, valid JSON-LD schemas to enhance your site's SEO and presence in Search Engine Results Pages (SERPs).

---

## 🚀 Key Features

- **WooCommerce Integration**: Optimized schema for Products, including support for Variable Products and Simple Products.
- **Default Schema Override**: Automatically disables default WooCommerce structured data to prevent duplication and validation errors.
- **Manual Overrides**: Set custom ratings, review counts, and brands at the individual product level or use global defaults.
- **Multiple Schema Types**:
  - **Organization / LocalBusiness**: Full support for store details, social links, addresses, and opening hours.
  - **WebSite**: Includes `SearchAction` for Sitelinks Searchbox.
  - **WebPage**: Enriches standard pages with technical metadata.
  - **Breadcrumbs**: Clean, hierarchical breadcrumb navigation schema.
  - **Articles**: Enhanced schema for Blog Postings.
  - **FAQ**: Structured data for FAQ sections to trigger rich snippets.
- **Dynamic Configuration**: Easy-to-use settings dashboard for global configurations.
- **Lightweight & Efficient**: Zero bloat, focused purely on high-quality JSON-LD output.

---

## 🛠 Installation

1. Upload the `jvm-schema` folder to your `/wp-content/plugins/` directory.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Navigate to **JVM Schema** in your WordPress admin sidebar to begin configuration.

---

## ⚙️ Configuration

The plugin is divided into several logical modules accessible via the settings page:

### 1. General & Website
Set your site name, URL, and enable the Sitelinks Searchbox (`SearchAction`).

### 2. Organization / Local Business
Provide your business details including:
- **Logo**: Upload via media library or provide a URL.
- **Social Links**: SameAs links for Facebook, Instagram, LinkedIn, etc.
- **Contact**: Phone, email, and physical address.
- **Opening Hours**: Configure per-day hours or a custom pattern.

### 3. WooCommerce Products
- **Global Defaults**: Set a default brand name, rating value (e.g., 5), and review count (e.g., 10) for products that don't have enough manual data.
- **Per-Product Settings**: Override ratings and brands directly in the product editor.

### 4. Breadcrumbs & Articles
Toggle these schemas globally and customize how home links appear in the breadcrumb trail.

---

## 💻 Requirements

- **WordPress**: 5.0+
- **PHP**: 7.4+
- **WooCommerce**: (Optional, required for Product Schema features)

---

## 📄 License

This plugin is licensed under the [GPL-2.0+](https://www.gnu.org/licenses/gpl-2.0.html) License.

---

Developed with ❤️ by **JVM**.
