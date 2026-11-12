# Destination-based shipping

Open Configuration > Shipping and edit a method. Set its default rate, optional free-shipping threshold, delivery estimate, country coverage and state/emirate overrides. Choose any country and enter its state, province or region. The GCC shortcut selects Bahrain, Kuwait, Oman, Qatar, Saudi Arabia and the United Arab Emirates. New rules have no default country unless coverage contains exactly one country.

A matching country and state row overrides the default price. A blank regional free threshold inherits the method threshold. Set a separate regional threshold to offer free shipping at a different merchandise total. Thresholds use merchandise totals after discounts. Restrict delivery to listed states to hide the method for unmatched or missing states; otherwise the default rate applies. Country coverage still applies to every override. Duplicate normalized country/state combinations are rejected.

UAE checkout uses an emirate selector. Backend matching accepts case and whitespace differences, common emirate codes (such as AE-DU) and supported Arabic names. Both checkout quotes and final order placement use the same provider. Existing orders retain their recorded shipping costs.
