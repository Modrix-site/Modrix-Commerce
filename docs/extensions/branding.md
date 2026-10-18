# Extension branding and categories

Extensions display the manifest `icon` (Lucide name) and optional `accent` color. To display an original brand logo, add `"logo": "resources/brand.png"` to module.json and include that file in your ZIP. PNG, JPEG and WebP images up to 256 KB are accepted. The logo must remain within the package; remote URLs and SVG uploads are not accepted. Admin screens load it as an embedded image without remote requests. Extensions without a logo retain their icon and accent.

The Extensions screen groups packages by their declared capabilities and feature names, with search and status filters. Payment gateways declaring `payment.gateway` are categorized under Payments; shipping providers under Shipping & delivery, and search providers under Catalog & merchandising. Packages may appear in multiple feature categories. Existing lifecycle and permissions are unchanged.

Bundled official wordmarks: Tabby https://tabby.ai/en-AE and Tamara https://tamara.co/en-sa. Their original SVG artwork is stored in public/images/extensions.
