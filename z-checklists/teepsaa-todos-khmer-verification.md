# Khmer Verification — Review Every Khmer String With a Native Speaker

How to use this checklist:

- Sit with a native Khmer speaker (ideally someone who shops online) and go
  page by page. Check off each phrase they confirm sounds natural.
- Each line shows: the Khmer text — the English meaning — and the code key
  in parentheses. If a phrase is wrong, note the correction next to it; the
  key (e.g. `nav_cart`) tells Claude exactly which line of `lang/km.php` to fix.
- Ask not just "is this correct?" but "is this what a Khmer shopping app
  would say?" — literal translations often sound stiff.
- The Header and Footer sections appear on every page — verify them first.

## Header (appears on every page)

- [ ] ការបង់ប្រាក់<br>បានដាក់ស្នើ — “Payment<br>submitted” (`js_st_pending`)
- [ ] ការបង់ប្រាក់<br>បានបញ្ជាក់ — “Payment<br>confirmed” (`js_st_paid`)
- [ ] បានបញ្ជូន — “Dispatched” (`js_st_dispatched`)
- [ ] បានដឹកជញ្ជូន — “Delivered” (`js_st_delivered`)
- [ ] បានបញ្ចប់ — “Completed” (`js_st_completed`)
- [ ] ស្នើសុំ<br>សំណង — “Refund<br>Requested” (`js_st_refund_requested`)
- [ ] ការប្រគល់<br>បានអនុម័ត — “Return<br>Approved” (`js_st_return_approved`)
- [ ] ការប្រគល់<br>បានផ្ញើ — “Return<br>Sent” (`js_st_return_dispatched`)
- [ ] ទំនិញ<br>បានទទួល — “Item<br>Received” (`js_st_return_received`)
- [ ] បានសងវិញ — “Refunded” (`js_st_refunded`)
- [ ] ការបញ្ជាទិញត្រូវបានលុបចោល — “Order cancelled” (`js_order_cancelled`)
- [ ] សំណងត្រូវបានបដិសេធ — “Refund rejected” (`js_refund_rejected`)
- [ ] វគ្គបានផុតកំណត់ — — “Session expired —” (`js_session_expired`)
- [ ] សូមចូលគណនីម្តងទៀត — “please log in again” (`js_login_again`)
- [ ] ការបញ្ជាទិញ #%s ត្រូវបានធ្វើបច្ចុប្បន្នភាព — “Order #%s updated” (`js_order_updated`)
- [ ] មិនអាចផ្ទុកឡើងវិញ — សូមព្យាយាមម្តងទៀត។ — “Could not refresh — try again.” (`js_refresh_error`)
- [ ] មិនទាន់មានការជូនដំណឹង — “No notifications yet” (`js_no_notifications`)
- [ ] កំពុងផ្ទុក… — “Loading…” (`js_loading`)
- [ ] ស្វែងរកទីផ្សារ — “search teepsaa” (`search_placeholder`)
- [ ] អ្នកគ្រប់គ្រង — “Admin” (`nav_admin`)
- [ ] កម្មង់ — “Orders” (`nav_orders`)
- [ ] ផ្សព្វផ្សាយ — “Marketing” (`nav_marketing`)
- [ ] មាតិកា — “Content” (`nav_content`)
- [ ] សារ — “Messages” (`nav_messages`)
- [ ] ការកំណត់ — “Settings” (`nav_settings`)
- [ ] ចេញ — “Logout” (`nav_logout`)
- [ ] ផលិតផល — “Products” (`nav_products`)
- [ ] ការវិភាគ — “Analytics” (`nav_vendor`)
- [ ] ការជូនដំណឹង — “Notifications” (`nav_notifications`)
- [ ] សម្គាល់ថាបានអានទាំងអស់ — “Mark all read” (`nav_mark_all_read`)
- [ ] បញ្ជីចង់បាន — “Wishlist” (`nav_wishlist`)
- [ ] ចូលគណនី — “Login” (`nav_login`)
- [ ] ភាសា — “Language” (`lang_label`)
- [ ] រូបិយប័ណ្ណ — “Currency” (`currency_label`)
- [ ] រទេះ — “Cart” (`nav_cart`)

## Footer (appears on every page)

- [ ] ចូលគណនី — “Sign in” (`footer_sign_in`)
- [ ] បង្កើតគណនី — “Create account” (`footer_create_account`)
- [ ] កម្មង់ — “Orders” (`nav_orders`)
- [ ] ផលិតផល — “Products” (`nav_products`)
- [ ] សារ — “Messages” (`nav_messages`)
- [ ] ការកំណត់ — “Settings” (`nav_settings`)
- [ ] បញ្ជីចង់បាន — “Wishlist” (`nav_wishlist`)
- [ ] គណនីរបស់ខ្ញុំ — “Your Account” (`footer_your_account`)
- [ ] ជំនួយ — “Help” (`footer_help`)
- [ ] មជ្ឈមណ្ឌលជំនួយ — “Help Center” (`footer_help_center`)
- [ ] ការដឹកជញ្ជូន — “Shipping” (`footer_shipping`)
- [ ] ការប្រគល់ទំនិញវិញ — “Returns” (`footer_returns`)
- [ ] គោលការណ៍ភាពឯកជន — “Privacy Policy” (`footer_privacy`)
- [ ] លក្ខខណ្ឌប្រើប្រាស់ — “Terms of Service” (`footer_terms`)
- [ ] ទីផ្សារ — “teepsaa” (`footer_brand`)
- [ ] អំពី — “About” (`footer_about`)
- [ ] ការងារ — “Careers” (`footer_careers`)
- [ ] លក់នៅទីផ្សារ — “Sell on teepsaa” (`footer_sell_on`)
- [ ] ទីផ្សារ។ រក្សាសិទ្ធិគ្រប់យ៉ាង។ — “teepsaa. All rights reserved.” (`footer_copyright`)

## Homepage `/`

- [ ] រកទិញតាមប្រភេទ — “Shop by category” (`home_shop_by_category`)
- [ ] ផលិតផលពិសេស — “Featured products” (`home_featured`)
- [ ] លក់ដាច់ជាងគេ — “Best sellers” (`home_best_sellers`)
- [ ] និយមក្នុងសប្តាហ៍នេះ — “Trending this week” (`home_trending`)
- [ ] ទំនិញថ្មី — “New arrivals” (`home_new_arrivals`)
- [ ] វាយតម្លៃខ្ពស់ — “Top rated” (`home_top_rated`)
- [ ] តម្លៃក្រោម ១៥ ដុល្លារ — “Under $15” (`home_under_15`)
- [ ] អ្នកប្រហែលជាចូលចិត្ត — “You might like” (`home_you_might_like`)
- [ ] បានមើលថ្មីៗ — “Recently viewed” (`home_recently_viewed`)

## Search `/search/`

- [ ] ថ្មីៗ — “Newest” (`sort_newest`)
- [ ] តម្លៃ៖ ទាបទៅខ្ពស់ — “Price: low to high” (`sort_price_asc`)
- [ ] តម្លៃ៖ ខ្ពស់ទៅទាប — “Price: high to low” (`sort_price_desc`)
- [ ] វាយតម្លៃខ្ពស់ — “Top rated” (`sort_rating`)
- [ ] និយមជាងគេ — “Most popular” (`sort_popular`)
- [ ] ប្រភេទ — “Category” (`search_category`)
- [ ] ចាប់ពី — “From” (`search_from`)
- [ ] តម្រង — “Filters” (`search_filters`)
- [ ] តម្រៀបតាម — “Sort by” (`search_sort_by`)
- [ ] ប្រភេទទាំងអស់ — “All categories” (`search_all_categories`)
- [ ] តម្លៃ (ដុល្លារ) — “Price (USD)” (`search_price_usd`)
- [ ] អប្បបរមា — “Min” (`search_price_min`)
- [ ] អតិបរមា — “Max” (`search_price_max`)
- [ ] អនុវត្ត — “Apply” (`search_apply`)
- [ ] ការវាយតម្លៃ — “Rating” (`search_rating`)
- [ ] ការវាយតម្លៃទាំងអស់ — “Any rating” (`search_any_rating`)
- [ ] ឡើងទៅ — “& up” (`search_rating_up`)
- [ ] លុបតម្រង — “Clear filters” (`search_clear`)
- [ ] ផលិតផល — “products” (`search_products`)
- [ ] សម្រាប់ — “for” (`search_for`)
- [ ] រកមិនឃើញផលិតផល។ — “No products found.” (`search_no_results`)
- [ ] សាកល្បងលុបតម្រងមួយចំនួន។ — “Try removing some filters.” (`search_no_results_hint`)

## Product detail `/product/`

- [ ] លក់ដោយ — “Sold by” (`product_sold_by`)
- [ ] ប្រភេទ — “Variants” (`product_variants`)
- [ ] ជ្រើសរើសជម្រើស — “Select options” (`product_select_options`)
- [ ] ជ្រើសរើសប្រភេទ — “Select a variant” (`product_select_variant`)
- [ ] មានស្តុក — “In stock” (`product_in_stock`)
- [ ] អស់ស្តុក — “Out of stock” (`product_out_of_stock`)
- [ ] បញ្ចូលរទេះ — “Add to cart” (`product_add_to_cart`)
- [ ] ចូលគណនីដើម្បីទិញ — “Login to buy” (`product_login_to_buy`)
- [ ] បានរក្សាទុក — “Saved” (`product_saved`)
- [ ] រក្សាទុក — “Save” (`product_save`)
- [ ] បានបញ្ចូលរទេះ — “Added to cart” (`product_added_to_cart`)
- [ ] បន្តទៅបង់ប្រាក់ — “Proceed to checkout” (`product_proceed_checkout`)
- [ ] មតិយោបល់ — “Reviews” (`product_reviews`)
- [ ] មិនទាន់មានមតិយោបល់ទេ។ — “No reviews yet.” (`product_no_reviews`)
- [ ] ការផ្សំនេះមិនមាន — “Combination not available” (`product_combo_unavailable`)
- [ ] សូមជ្រើសរើស — “Please select” (`product_please_select`)
- [ ] និង — “and” (`product_and`)
- [ ] សូមអភ័យទោស ជម្រើសនោះអស់ស្តុក។ — “Sorry, that option is out of stock.” (`product_option_oos`)
- [ ] សូមជ្រើសរើសប្រភេទ។ — “Please select a variant.” (`product_please_select_variant`)
- [ ] សូមអភ័យទោស ប្រភេទនោះអស់ស្តុក។ — “Sorry, that variant is out of stock.” (`product_variant_oos`)

## Business page `/business/`

- [ ] មតិយោបល់ — “review” (`store_review`)
- [ ] មតិយោបល់ — “reviews” (`store_reviews`)
- [ ] ផលិតផល — “Products” (`vendor_products`)
- [ ] ក្នុងស្តុក — “in stock” (`store_in_stock`)
- [ ] អស់ស្តុក — “Out of stock” (`product_out_of_stock`)

## Cart `/cart/`

- [ ] រទេះរបស់ខ្ញុំ — “Your cart” (`cart_title`)
- [ ] រទេះរបស់អ្នកទទេ។ — “Your cart is empty.” (`cart_empty`)
- [ ] រកមើលហាង — “Browse businesses” (`cart_browse`)
- [ ] ក្នុងមួយ — “each” (`cart_each`)
- [ ] ធ្វើបច្ចុប្បន្នភាព — “Update” (`cart_update`)
- [ ] លុបចេញ — “Remove” (`cart_remove`)
- [ ] សរុបរង — “Subtotal” (`cart_subtotal`)
- [ ] ការដឹកជញ្ជូន Grab — “Grab delivery” (`cart_grab_delivery`)
- [ ] បំពេញអាសយដ្ឋានដើម្បីមើលការប៉ាន់ស្មាន — “set your address to see estimate” (`cart_set_address`)
- [ ] ដាក់ pin ដើម្បីប្រហាញ — “set your pin for estimate” (`cart_set_pin`)
- [ ] មិនអាចដឹកជញ្ជូនបាន — “Delivery unavailable” (`cart_delivery_unavailable`)
- [ ] គ.ម.ឆ្ងាយ (ច្រើនបំផុត — “km away (max” (`cart_km_away`)
- [ ] ការដឹកជញ្ជូន Grab (ប្រហាញ) — “Est. Grab delivery” (`cart_delivery_est`)
- [ ] បង់ជាសាច់ប្រាក់ — “COD” (`cart_delivery_cod`)
- [ ] សរុប — “Total” (`cart_total`)
- [ ] ការដឹកជញ្ជូន Grab ត្រូវបង់ជាសាច់ប្រាក់ដល់អ្នកបើកបរ។ — “Grab delivery is paid cash to the driver on arrival.” (`checkout_grab_note`)
- [ ] បង់ប្រាក់ — “Checkout” (`cart_checkout`)

## Checkout `/checkout/`

- [ ] ការកម្មង់ត្រូវបានដាក់! — “Order placed!” (`checkout_order_placed`)
- [ ] មើលការកម្មង់របស់ខ្ញុំ — “View my orders” (`checkout_view_orders`)
- [ ] រទេះរបស់អ្នកទទេ។ — “Your cart is empty.” (`checkout_empty`)
- [ ] រកមើលហាង — “Browse businesses” (`cart_browse`)
- [ ] មិនអាចដឹកជញ្ជូនសម្រាប់៖ — “Delivery unavailable for:” (`checkout_oos_pre`)
- [ ] — នៅឆ្ងាយពេកពីអាសយដ្ឋានរបស់អ្នក។ សូមលុបទំនិញទាំងនោះដើម្បីបន្ត។ — “— too far from your address. Remove those items to continue.” (`checkout_oos_post`)
- [ ] ដឹកជញ្ជូនទៅ៖ — “Delivering to:” (`checkout_delivering_to`)
- [ ] អាសយដ្ឋានដែលបានរក្សាទុករបស់អ្នក — “your saved address” (`checkout_your_saved_address`)
- [ ] ផ្លាស់ប្តូរ — “Change” (`checkout_change`)
- [ ] អាសយដ្ឋានដែលបានរក្សាទុក — “Saved address” (`checkout_saved_address`)
- [ ] លំនាំដើម — “Default” (`checkout_default`)
- [ ] ប្រើអាសយដ្ឋាននេះ — “Use this address” (`checkout_use_address`)
- [ ] សង្ខេបការកម្មង់ — “Order summary” (`checkout_order_summary`)
- [ ] សរុបរង — “Subtotal” (`checkout_subtotal`)
- [ ] ប្រហាញ — “Est.” (`checkout_est`)
- [ ] បង់ជាសាច់ប្រាក់ — “COD” (`checkout_delivery_cod`)
- [ ] ការដឹកជញ្ជូន — ក្រៅតំបន់ — “Delivery — out of range” (`checkout_delivery_range`)
- [ ] ការដឹកជញ្ជូន Grab (ប្រហាញ) — “Est. Grab delivery” (`checkout_delivery`)
- [ ] ដាក់ pin ដើម្បីប្រហាញ — “Set pin for estimate” (`checkout_set_pin`)
- [ ] បានអនុវត្តកូដ៖ — “Code applied:” (`checkout_coupon_applied`)
- [ ] លុប — “Remove” (`checkout_coupon_remove`)
- [ ] កូដបញ្ចុះតម្លៃ — “Discount code” (`checkout_coupon_placeholder`)
- [ ] អនុវត្ត — “Apply” (`checkout_coupon_apply`)
- [ ] សរុប — “Total” (`checkout_total`)
- [ ] ការដឹកជញ្ជូន Grab ត្រូវបង់<strong>ដោយផ្ទាល់ដល់អ្នកបើកបរពេលមកដល់</strong> — សាច់ប្រាក់ ឬ QR code។ វាដាច់ដោយឡែកពីការបង់ប្រាក់ teepsaa របស់អ្នក។ — “Grab delivery is paid <strong>directly to the driver on arrival</strong> — cash or QR code. This is separate from your teepsaa payment.” (`checkout_cod_note1`)
- [ ] ថ្លៃសេវាដែលបង្ហាញគឺជាការប៉ាន់ស្មាន ផ្អែកលើអាសយដ្ឋានរបស់អ្នក និងទីតាំងអាជីវកម្ម។ — “Fee shown is an estimate based on your saved address and the business location.” (`checkout_cod_note2`)
- [ ] បង់តាម ABA — “Pay with ABA” (`checkout_pay_aba`)
- [ ] ស្កេន QR code ខាងក្រោមក្នុងកម្មវិធី ABA របស់អ្នក ហើយបង់ឲ្យបានត្រឹមត្រូវ %s។ បន្ទាប់មកចុច «ខ្ញុំបានបង់ប្រាក់» ដើម្បីដាក់កម្មង់។ — “Scan the QR code below in your ABA app and pay exactly %s. Then click "I've paid" to place your order.” (`checkout_scan_instructions`)
- [ ] លុបទំនិញដែលក្រៅតំបន់ចេញពីរទេះមុននឹងបង់ប្រាក់។ — “Remove out-of-range items from your cart before paying.” (`checkout_remove_range`)
- [ ] កូដ QR ABA នឹងមកដល់ឆាប់ៗ — “ABA QR code coming soon” (`checkout_aba_coming_soon`)
- [ ] ការណែនាំដឹកជញ្ជូន — លេខកូដច្រកទ្វារ ទូរស័ព្ទពេលមកដល់ ។ល។ (ស្រេចចិត្ត) — “Delivery instructions — gate code, call on arrival, etc. (optional)” (`checkout_notes_placeholder`)
- [ ] បានបង់ — ដាក់ការកម្មង់ — “I've paid — place my order” (`checkout_ive_paid`)
- [ ] កម្មង់របស់អ្នកនឹងត្រូវបញ្ជាក់ បន្ទាប់ពីយើងផ្ទៀងផ្ទាត់ការបង់ប្រាក់។ ជាធម្មតាចំណាយពេលតិចជាង ១ ម៉ោង។ — “Your order will be confirmed once we verify your payment. This usually takes less than 1 hour.” (`checkout_confirm_note`)
- [ ] អំពីការដឹកជញ្ជូន Grab — “About your Grab delivery” (`checkout_about_grab`)
- [ ] អ្នកបង់ប្រាក់ឲ្យអ្នកបើកបរ Grab <strong>ដោយផ្ទាល់ពេលមកដល់</strong> — សាច់ប្រាក់ ឬ QR code។ វា<strong>ដាច់ដោយឡែក</strong>ពីការបង់ប្រាក់កម្មង់ teepsaa របស់អ្នក។ — “You pay the Grab driver <strong>directly on arrival</strong> — cash or QR code. This is <strong>separate</strong> from your teepsaa order payment.” (`checkout_grab_modal_1`)
- [ ] ថ្លៃដឹកជញ្ជូនដែលបង្ហាញគឺជា<strong>ការប៉ាន់ស្មាន</strong> គណនាពីអាសយដ្ឋានរបស់អ្នក និងទីតាំងអាជីវកម្ម។ ថ្លៃ Grab ជាក់ស្តែងអាចប្រែប្រួល។ — “The delivery fee shown is an <strong>estimate</strong> calculated from your saved address and the business location. The actual Grab fee may vary.” (`checkout_grab_modal_2`)
- [ ] យល់ព្រម — “I understand” (`checkout_i_understand`)

## Wishlist `/wishlist/`

- [ ] ទំនិញដែលបានរក្សាទុក — “Saved items” (`wishlist_title`)
- [ ] មិនទាន់មានអ្វីរក្សាទុកទេ។ — “Nothing saved yet.” (`wishlist_empty`)
- [ ] រកមើលផលិតផល — “Browse products” (`wishlist_browse`)
- [ ] មិនមាននៅពេលនេះទេ — “Currently unavailable” (`wishlist_unavailable`)

## Buyer login `/login-buyer/`

- [ ] ចូលគណនី — “Log in” (`login_title`)
- [ ] អ៊ីមែល — “Email” (`login_email`)
- [ ] ពាក្យសម្ងាត់ — “Password” (`login_password`)
- [ ] ចូល — “Log in” (`login_submit`)
- [ ] មិនទាន់មានគណនី? — “No account?” (`login_no_account`)
- [ ] ចុះឈ្មោះ — “Register” (`login_register`)
- [ ] ភ្លេចពាក្យសម្ងាត់? — “Forgot password?” (`login_forgot`)

## Vendor login `/login-vendor/`

- [ ] ចូលគណនីអ្នកលក់ — “Vendor log in” (`login_vendor_title`)
- [ ] អ៊ីមែល — “Email” (`login_email`)
- [ ] ពាក្យសម្ងាត់ — “Password” (`login_password`)
- [ ] ចូល — “Log in” (`login_submit`)
- [ ] មិនទាន់មានគណនី? — “No account?” (`login_no_account`)
- [ ] ចុះឈ្មោះជាអ្នកលក់ — “Register as a vendor” (`register_as_vendor`)
- [ ] ភ្លេចពាក្យសម្ងាត់? — “Forgot password?” (`login_forgot`)

## Buyer registration `/register-buyer/`

- [ ] បង្កើតគណនី — “Create an account” (`register_title`)
- [ ] ឈ្មោះពេញ — “Full name” (`register_name`)
- [ ] អ៊ីមែល — “Email” (`register_email`)
- [ ] ពាក្យសម្ងាត់ — “Password” (`register_password`)
- [ ] បញ្ជាក់ពាក្យសម្ងាត់ — “Confirm password” (`register_confirm`)
- [ ] ចុះឈ្មោះ — “Register” (`register_submit`)
- [ ] ដោយចុះឈ្មោះ អ្នកយល់ព្រមតាម%s និង%sរបស់យើង។ — “By registering you agree to our %s and %s.” (`auth_agree`)
- [ ] លក្ខខណ្ឌប្រើប្រាស់ — “Terms of Service” (`footer_terms`)
- [ ] គោលការណ៍ភាពឯកជន — “Privacy Policy” (`footer_privacy`)
- [ ] មានគណនីរួចហើយ? — “Already have an account?” (`register_have_account`)
- [ ] ចូលគណនី — “Log in” (`register_login`)

## Vendor registration `/register-vendor/`

- [ ] ចង់លក់នៅទីផ្សារ? — “Want to sell on teepsaa?” (`sell_cta_title`)
- [ ] អ្នកកំពុងចូលដោយគណនី<strong>អ្នកទិញ</strong>។ គណនីអ្នកលក់ដាច់ដោយឡែក ដូច្នេះដើម្បីចាប់ផ្តើមលក់ អ្នកត្រូវចុះឈ្មោះគណនីអ្នកលក់ដោយប្រើអ៊ីមែលផ្សេង។ — “You're signed in with a <strong>buyer</strong> account. Vendor accounts are separate, so to start selling you'll need to register a vendor account with a different email address.” (`sell_cta_body1`)
- [ ] សូមចេញពីគណនីជាមុនសិន បន្ទាប់មកបង្កើតគណនីអ្នកលក់របស់អ្នក។ — “Log out first, then create your vendor account.” (`sell_cta_body2`)
- [ ] ចេញពីគណនី និងចុះឈ្មោះជាអ្នកលក់ — “Log out &amp; register as a vendor” (`sell_cta_button`)
- [ ] ប្តូរចិត្ត? — “Changed your mind?” (`sell_cta_back`)
- [ ] ត្រឡប់ទៅគណនីរបស់អ្នក — “Back to your account” (`sell_cta_back_link`)
- [ ] ចុះឈ្មោះជាអ្នកលក់ — “Register as a vendor” (`register_as_vendor`)
- [ ] ឈ្មោះពេញ — “Full name” (`register_name`)
- [ ] អ៊ីមែល — “Email” (`register_email`)
- [ ] ពាក្យសម្ងាត់ — “Password” (`register_password`)
- [ ] បញ្ជាក់ពាក្យសម្ងាត់ — “Confirm password” (`register_confirm`)
- [ ] កូដប្រម៉ូសិន — “Promo code” (`register_promo`)
- [ ] (ស្រេចចិត្ត) — “(optional)” (`form_optional`)
- [ ] ចុះឈ្មោះ — “Register” (`register_submit`)
- [ ] ដោយចុះឈ្មោះ អ្នកយល់ព្រមតាម%s និង%sរបស់យើង។ — “By registering you agree to our %s and %s.” (`auth_agree`)
- [ ] លក្ខខណ្ឌប្រើប្រាស់ — “Terms of Service” (`footer_terms`)
- [ ] គោលការណ៍ភាពឯកជន — “Privacy Policy” (`footer_privacy`)
- [ ] មានគណនីរួចហើយ? — “Already have an account?” (`register_have_account`)
- [ ] ចូលគណនីអ្នកលក់ — “Vendor log in” (`login_vendor_title`)

## Forgot/reset password (buyer + vendor)

- [ ] កំណត់ពាក្យសម្ងាត់ឡើងវិញ — “Reset password” (`rp_title`)
- [ ] ពាក្យសម្ងាត់ថ្មី — “New password” (`settings_new_pw`)
- [ ] បញ្ជាក់ពាក្យសម្ងាត់ថ្មី — “Confirm new password” (`settings_confirm_pw`)
- [ ] ភ្លេចពាក្យសម្ងាត់? — “Forgot your password?” (`fp_title`)
- [ ] ត្រឡប់ទៅចូលគណនី — “Back to log in” (`auth_back_login`)
- [ ] បញ្ចូលអាសយដ្ឋានអ៊ីមែលអ្នកលក់របស់អ្នក ហើយយើងនឹងផ្ញើតំណកំណត់ឡើងវិញឱ្យអ្នក។ — “Enter your vendor email address and we'll send you a reset link.” (`fp_hint_vendor`)
- [ ] អ៊ីមែល — “Email” (`login_email`)
- [ ] ផ្ញើតំណកំណត់ឡើងវិញ — “Send reset link” (`fp_send`)
- [ ] បញ្ចូលអាសយដ្ឋានអ៊ីមែលរបស់អ្នក ហើយយើងនឹងផ្ញើតំណកំណត់ឡើងវិញឱ្យអ្នក។ — “Enter your email address and we'll send you a reset link.” (`fp_hint_buyer`)

## Email verification `/verify-email/` + `/resend-verification/`

- [ ] ផ្ទៀងផ្ទាត់អ៊ីមែលរបស់អ្នក — “Verify your email” (`rv_title`)
- [ ] អាសយដ្ឋានអ៊ីមែលរបស់អ្នកត្រូវបានផ្ទៀងផ្ទាត់។ — “Your email address is verified.” (`rv_verified`)
- [ ] មិនបានមកដល់? — “Didn't arrive?” (`rv_didnt_arrive`)
- [ ] ផ្ញើឡើងវិញ — “Resend” (`rv_resend`)
- [ ] យើងបានផ្ញើតំណផ្ទៀងផ្ទាត់ទៅ %s។ សូមពិនិត្យប្រអប់សំបុត្ររបស់អ្នក ហើយចុចលើតំណដើម្បីធ្វើឱ្យគណនីរបស់អ្នកសកម្ម។ — “We sent a verification link to %s. Check your inbox and click the link to activate your account.” (`rv_sent`)
- [ ] ផ្ញើអ៊ីមែលផ្ទៀងផ្ទាត់ឡើងវិញ — “Resend verification email” (`rv_resend_email`)
- [ ] ពិនិត្យអ៊ីមែលរបស់អ្នក — “Check your email” (`ve_title`)
- [ ] យើងបានផ្ញើកូដ ៦ ខ្ទង់ទៅ %s។ សូមបញ្ចូលវាខាងក្រោម។ — “We sent a 6-digit code to %s. Enter it below.” (`ve_sent`)
- [ ] ផ្ទៀងផ្ទាត់ — “Verify” (`ve_verify`)
- [ ] មិនបានទទួល? — “Didn't get it?” (`ve_didnt_get`)
- [ ] ផ្ញើកូដឡើងវិញ — “Resend code” (`ve_resend_code`)

## Buyer dashboard & orders `/dashboard-buyer/`

- [ ] ការកម្មង់របស់ខ្ញុំ — “My Orders” (`orders_title`)
- [ ] អ្នកមិនទាន់បានដាក់ការកម្មង់ណាមួយទេ។ — “You haven't placed any orders yet.” (`orders_empty`)
- [ ] កំពុងរង់ចាំបញ្ជាក់ការបង់ប្រាក់ — ជាធម្មតាក្នុងរយៈពេល ១ ម៉ោង។ — “Awaiting payment confirmation — usually within 1 hour.” (`orders_awaiting_payment`)
- [ ] ទុកមតិយោបល់សម្រាប់ការកម្មង់នេះ — “Leave a review for this order” (`orders_leave_review`)
- [ ] ព័ត៌មានកម្មង់ — “Order info” (`order_info`)
- [ ] កាលបរិច្ឆេទ — “Date” (`order_date`)
- [ ] អាជីវកម្ម — “Business” (`order_business`)
- [ ] អ្នកលក់ — “Vendor” (`order_vendor`)
- [ ] តាមដាន — “Tracking” (`order_tracking`)
- [ ] តាមដានការដឹកជញ្ជូន ↗ — “Track delivery ↗” (`order_track_delivery`)
- [ ] ទំនិញ — “Items” (`order_items`)
- [ ] ផលិតផល — “Product” (`order_col_product`)
- [ ] ចំនួន — “Qty” (`order_col_qty`)
- [ ] តម្លៃ — “Price” (`order_col_price`)
- [ ] សរុប — “Total” (`order_col_total`)
- [ ] សរុបរង — “Subtotal” (`checkout_subtotal`)
- [ ] បានអនុវត្តកូដ៖ — “Code applied:” (`checkout_coupon_applied`)
- [ ] ការដឹកជញ្ជូន — “Delivery” (`order_delivery`)
- [ ] សរុប — “Total” (`checkout_total`)
- [ ] មតិយោបល់ — “Reviews” (`product_reviews`)
- [ ] បានវាយតម្លៃ ✓ — “Reviewed ✓” (`order_reviewed`)
- [ ] ទុកមតិយោបល់ — “Leave a review” (`order_leave_review`)
- [ ] ស្ថានភាព — “Status” (`order_status_heading`)
- [ ] បញ្ជាក់ការទទួល — “Confirm delivery” (`order_confirm_delivery`)
- [ ] ជម្រើស — “Options” (`order_options`)
- [ ] មានបញ្ហាជាមួយកម្មង់? — “Issue with order?” (`order_issue`)
- [ ] អ្នកនឹងត្រូវប្រគល់ទំនិញវិញតាម Grab ដោយសោហ៊ុយផ្ទាល់ខ្លួន។ សំណងរបស់អ្នកនឹងជា %s (ថ្លៃដឹកជញ្ជូនមិនអាចសងវិញបានទេ)។ — “You will need to return the item via Grab at your own cost. Your refund will be %s (delivery fee is non-refundable).” (`order_refund_info`)
- [ ] អាចប្រើបានរហូតដល់ %s។ — “Available until %s.” (`order_available_until`)
- [ ] — ជ្រើសរើសមូលហេតុ — — “— Select a reason —” (`order_select_reason`)
- [ ] ទំនិញមិនដូចការពិពណ៌នា — “Item not as described” (`order_reason_1`)
- [ ] ទទួលបានទំនិញខុស — “Wrong item received” (`order_reason_2`)
- [ ] ទំនិញមកដល់ខូច — “Item arrived damaged” (`order_reason_3`)
- [ ] ខ្វះគ្រឿងបន្លាស់ ឬឧបករណ៍ — “Missing parts or accessories” (`order_reason_4`)
- [ ] គុណភាពមិនដូចការរំពឹង — “Quality not as expected” (`order_reason_5`)
- [ ] ផ្សេងទៀត… — “Other…” (`order_reason_other`)
- [ ] ពិពណ៌នាបញ្ហា… — “Describe the issue…” (`order_describe_issue`)
- [ ] ស្នើសុំសំណង — “Request Refund” (`order_request_refund`)
- [ ] រយៈពេលស្នើសំណងបានបិទ។ — “Refund window has closed.” (`order_refund_closed`)
- [ ] ផ្ញើទំនិញត្រឡប់ — “Send item back” (`order_send_back`)
- [ ] ផ្ញើទៅ៖ — “Send to:” (`order_send_to`)
- [ ] ខ្ចប់ទំនិញ ហើយផ្ញើត្រឡប់វិញតាម Grab ដោយសោហ៊ុយផ្ទាល់ខ្លួន។ បិទភ្ជាប់តំណតាមដាន Grab ខាងក្រោមនៅពេលបានផ្ញើ។ — “Pack the item and send it back via Grab at your cost. Paste the Grab tracking link below once dispatched.” (`order_return_instructions`)
- [ ] បិទភ្ជាប់តំណតាមដានការប្រគល់វិញ Grab… — “Paste Grab return tracking URL…” (`order_return_url_placeholder`)
- [ ] សម្គាល់ថាបានផ្ញើត្រឡប់ — “Mark return dispatched” (`order_mark_dispatched`)

## Buyer settings `/dashboard-buyer/settings/`

- [ ] ការកំណត់ — “Settings” (`settings_title`)
- [ ] គណនី — “Account” (`settings_tab_account`)
- [ ] អាសយដ្ឋាន — “Address” (`settings_tab_address`)
- [ ] ពាក្យសម្ងាត់ — “Password” (`settings_password_heading`)
- [ ] លុបគណនី — “Delete account” (`settings_delete_account`)
- [ ] ជ្រើសរូបភាព — “Choose photo” (`settings_choose_photo`)
- [ ] លុបចេញ — “Remove” (`settings_remove_photo`)
- [ ] JPG ឬ PNG ទំហំអតិបរមា ២MB។ — “JPG or PNG, max 2MB.” (`settings_photo_hint`)
- [ ] ពណ៌អ្នកប្រើ — “Avatar color” (`settings_avatar_color`)
- [ ] — បង្ហាញនៅពេលគ្មានរូបភាព — “— shown when no photo is set” (`settings_avatar_hint`)
- [ ] ឈ្មោះពេញ — “Full name” (`settings_full_name`)
- [ ] អ៊ីមែល — “Email” (`settings_email`)
- [ ] មិនអាចផ្លាស់ប្តូរអ៊ីមែលនៅទីនេះ។ ទាក់ទងជំនួយ។ — “Email cannot be changed here. Contact support.” (`settings_email_hint`)
- [ ] លេខទូរស័ព្ទ — “Phone” (`settings_phone`)
- [ ] រក្សាទុក — “Save” (`settings_save`)
- [ ] អាសយដ្ឋានដឹកជញ្ជូន — “Delivery address” (`settings_delivery_address`)
- [ ] មិនទាន់រក្សាទុកអាសយដ្ឋានទេ។ — “No address saved yet.” (`settings_no_address`)
- [ ] កែអាសយដ្ឋាន — “Edit address” (`settings_address_edit`)
- [ ] លេខទូរស័ព្ទ — “Phone number” (`settings_phone_number`)
- [ ] លេខផ្ទះ / ល្វែង — “House / Unit #” (`settings_address_house`)
- [ ] ផ្លូវ — “Street” (`settings_street`)
- [ ] ជាន់ / ហាង / ចំណាំ — “Floor / Unit / Landmark” (`settings_address_floor`)
- [ ] ខណ្ឌ — “Khan” (`settings_address_khan`)
- [ ] ជ្រើសរើសខណ្ឌ — “Select Khan” (`settings_select_khan`)
- [ ] សង្កាត់ — “Sangkat” (`settings_address_sangkat`)
- [ ] ជ្រើសរើសសង្កាត់ — “Select Sangkat” (`settings_select_sangkat`)
- [ ] ដាក់ pin — “Drop pin” (`settings_address_drop_pin`)
- [ ] — ចុចលើផែនទីដើម្បីកំណត់ទីតាំងដឹកជញ្ជូនច្បាស់លាស់ — “— click the map to set your precise delivery location” (`settings_drop_pin_hint`)
- [ ] មិនទាន់កំណត់ pin — “No pin set” (`settings_no_pin`)
- [ ] រក្សាទុកអាសយដ្ឋាន — “Save address” (`settings_save_address`)
- [ ] អាសយដ្ឋានដែលបានរក្សាទុក — “Saved addresses” (`settings_saved_addresses`)
- [ ] គ្មានឈ្មោះ — “Unnamed” (`settings_unnamed`)
- [ ] លំនាំដើម — “Default” (`settings_address_default`)
- [ ] កំណត់ជាលំនាំដើម — “Set as default” (`settings_set_default`)
- [ ] + បន្ថែមអាសយដ្ឋានថ្មី — “+ Add new address” (`settings_address_add`)
- [ ] ស្លាក — “Label” (`settings_address_label`)
- [ ] ពាក្យសម្ងាត់បច្ចុប្បន្ន — “Current password” (`settings_current_pw`)
- [ ] ពាក្យសម្ងាត់ថ្មី — “New password” (`settings_new_pw`)
- [ ] យ៉ាងហោចណាស់ ៨ តួអក្សរ។ — “At least 8 characters.” (`settings_pw_hint`)
- [ ] បញ្ជាក់ពាក្យសម្ងាត់ថ្មី — “Confirm new password” (`settings_confirm_pw`)
- [ ] ធ្វើបច្ចុប្បន្នភាពពាក្យសម្ងាត់ — “Update password” (`settings_update_pw`)
- [ ] ការលុបគណនីរបស់អ្នកគឺជាអចិន្ត្រៃយ៍។ ប្រវត្តិការកម្មង់របស់អ្នកនឹងត្រូវលុបចេញ។ — “Deleting your account is permanent. Your order history will be removed.” (`settings_delete_warning`)
- [ ] បញ្ជាក់ពាក្យសម្ងាត់ — “Confirm your password” (`settings_confirm_pw_label`)
- [ ] លុបគណនីរបស់ខ្ញុំ — “Delete my account” (`settings_delete_confirm`)

## Vendor dashboard `/dashboard-vendor/`

- [ ] បានអនុម័ត — “Approved” (`vendor_biz_approved`)
- [ ] បានបដិសេធ — “Rejected” (`vendor_biz_rejected`)
- [ ] កំពុងរង់ចាំ — “Pending” (`vendor_biz_pending`)
- [ ] អាជីវកម្មរបស់ខ្ញុំ — “My Business” (`vendor_my_business`)
- [ ] + ដាក់ស្នើអាជីវកម្ម — “+ Submit a business” (`vendor_submit_biz`)
- [ ] សាកល្បងដោយមិនគិតថ្លៃ ០% — “0% platform fee trial active” (`vendor_trial_active`)
- [ ] — teepsaa មិនយកកម្រៃជើងសារលើការលក់របស់អ្នកទេ។ — “— teepsaa is taking no commission on your sales.” (`vendor_trial_no_commission`)
- [ ] ការសាកល្បងបញ្ចប់នៅ %s។ — “Trial ends %s.” (`vendor_trial_ends`)
- [ ] ដំណើរការលក់ — “Sales progress” (`vendor_sales_progress`)
- [ ] %s ក្នុងចំណោម %s — “%s of %s” (`vendor_of`)
- [ ] រយៈពេលបានបញ្ចប់ — ការសាកល្បងបន្តរហូតដល់អ្នកឈានដល់ការលក់ %s។ — “Time period has ended — trial continues until you reach %s in sales.” (`vendor_trial_time_ended`)
- [ ] កម្រៃធម្មតាចាប់ផ្តើមបន្ទាប់ពី %s និងការលក់ %s — មួយណាចុងក្រោយ។ — “Normal fees begin after %s and %s in sales — whichever comes last.” (`vendor_trial_normal_fees`)
- [ ] ស្ថិតិ — “Analytics” (`vendor_analytics`)
- [ ] ចំណូលសរុបទាំងអស់ — “All-time revenue” (`vendor_all_time_rev`)
- [ ] ចំណូលខែនេះ — “This month revenue” (`vendor_this_month_revenue`)
- [ ] កម្មង់សរុប — “Total orders” (`vendor_total_orders`)
- [ ] កម្មង់ខែនេះ — “This month orders” (`vendor_this_month_orders`)
- [ ] លក់ដាច់ជាងគេ — “Best sellers” (`vendor_best_sellers`)
- [ ] ផលិតផល — “Product” (`vendor_col_product`)
- [ ] ចំនួនលក់ — “Units sold” (`vendor_units_sold`)
- [ ] ចំណូល — “Revenue” (`vendor_col_revenue`)
- [ ] បញ្ចប់ការដឹកជញ្ជូនដំបូងដើម្បីមើលស្ថិតិ។ — “Complete your first delivery to see analytics here.” (`vendor_analytics_empty`)
- [ ] ការកម្មង់ — “Orders” (`vendor_orders`)
- [ ] មិនទាន់មានការកម្មង់ទេ។ — “No orders yet.” (`vendor_no_orders`)
- [ ] ផលិតផល — “Products” (`vendor_products`)
- [ ] ដាក់ស្នើអាជីវកម្មដើម្បីចាប់ផ្តើមបន្ថែមផលិតផល។ — “Submit a business to start adding products.” (`vendor_submit_to_add`)
- [ ] មិនទាន់មានផលិតផលទេ។ — “No products yet.” (`vendor_no_products`)
- [ ] បន្ថែមផលិតផលដំបូងរបស់អ្នក — “Add your first product” (`vendor_add_product`)
- [ ] ឈ្មោះ — “Name” (`vendor_col_name`)
- [ ] តម្លៃ — “Price” (`vendor_col_price`)
- [ ] ស្តុក — “Stock” (`vendor_col_stock`)
- [ ] ស្ថានភាព — “Status” (`vendor_col_status`)
- [ ] ជិតអស់ — “Low” (`vendor_stock_low`)
- [ ] អស់ — “Out” (`vendor_stock_out`)
- [ ] សកម្ម — “Active” (`vendor_status_active`)
- [ ] អសកម្ម — “Inactive” (`vendor_status_inactive`)

## Vendor settings `/dashboard-vendor/settings/`

- [ ] ការកំណត់ — “Settings” (`settings_title`)
- [ ] គណនី — “Account” (`settings_tab_account`)
- [ ] អាសយដ្ឋាន — “Address” (`vendor_settings_tab_address`)
- [ ] អាជីវកម្ម — “Business” (`vendor_settings_tab_business`)
- [ ] QR ធនាគារ — “Bank QR” (`vendor_settings_tab_bank`)
- [ ] ពាក្យសម្ងាត់ — “Password” (`settings_password_heading`)
- [ ] លុបគណនី — “Delete account” (`settings_delete_account`)
- [ ] ជ្រើសរូបភាព — “Choose photo” (`settings_choose_photo`)
- [ ] លុបចេញ — “Remove” (`settings_remove_photo`)
- [ ] JPG ឬ PNG ទំហំអតិបរមា ២MB។ — “JPG or PNG, max 2MB.” (`settings_photo_hint`)
- [ ] ពណ៌អ្នកប្រើ — “Avatar color” (`settings_avatar_color`)
- [ ] — បង្ហាញនៅពេលគ្មានរូបភាព — “— shown when no photo is set” (`settings_avatar_hint`)
- [ ] ទំនាក់ទំនងអ្នកលក់ — ឈ្មោះពេញ — “Vendor Contact — Full Name” (`vendor_contact_name`)
- [ ] ទំនាក់ទំនងអ្នកលក់ — អ៊ីមែល — “Vendor Contact — Email” (`vendor_contact_email`)
- [ ] មិនអាចផ្លាស់ប្តូរអ៊ីមែលនៅទីនេះ។ ទាក់ទងជំនួយ។ — “Email cannot be changed here. Contact support.” (`settings_email_hint`)
- [ ] ទំនាក់ទំនងអ្នកលក់ — ទូរស័ព្ទ — “Vendor Contact — Phone” (`vendor_contact_phone`)
- [ ] រក្សាទុក — “Save” (`settings_save`)
- [ ] អ្នកមិនទាន់បានដាក់ស្នើអាជីវកម្មទេ។ — “You haven't submitted a business yet.” (`vendor_no_business`)
- [ ] ដាក់ស្នើនៅទីនេះ។ — “Submit one here.” (`vendor_submit_one`)
- [ ] មិនទាន់រក្សាទុកអាសយដ្ឋានទេ។ — “No address saved yet.” (`settings_no_address`)
- [ ] កែអាសយដ្ឋាន — “Edit address” (`settings_address_edit`)
- [ ] លេខផ្ទះ / ល្វែង — “House / Unit #” (`settings_address_house`)
- [ ] ផ្លូវ — “Street” (`settings_street`)
- [ ] ជាន់ / ហាង / ចំណាំ — “Floor / Unit / Landmark” (`settings_address_floor`)
- [ ] ខណ្ឌ — “Khan” (`settings_address_khan`)
- [ ] ជ្រើសរើសខណ្ឌ — “Select Khan” (`settings_select_khan`)
- [ ] សង្កាត់ — “Sangkat” (`settings_address_sangkat`)
- [ ] ជ្រើសរើសសង្កាត់ — “Select Sangkat” (`settings_select_sangkat`)
- [ ] ចំណុចផែនទី — “Map pin” (`vendor_map_pin`)
- [ ] — ចុចដើម្បីកំណត់ទីតាំងអាជីវកម្មពិតប្រាកដរបស់អ្នក — “— click to set your exact business location” (`vendor_map_pin_hint`)
- [ ] មិនទាន់កំណត់ pin — មិនអាចគណនាចម្ងាយដឹកជញ្ជូនដោយគ្មាន pin — “No pin set — delivery distance cannot be calculated without a pin” (`vendor_no_pin_full`)
- [ ] រក្សាទុកអាសយដ្ឋាន — “Save address” (`settings_save_address`)
- [ ] ឈ្មោះអាជីវកម្ម — “Business name” (`vendor_settings_biz_name`)
- [ ] (ខ្មែរ — ស្រេចចិត្ត) — “(Khmer — optional)” (`form_km_field`)
- [ ] ការពិពណ៌នា — “Description” (`vendor_settings_description`)
- [ ] ប្រាប់អតិថិជនអំពីអាជីវកម្មរបស់អ្នក… — “Tell customers about your business…” (`vendor_biz_desc_placeholder`)
- [ ] ប្រភេទ — “Categories” (`vendor_settings_categories`)
- [ ] — ជ្រើសរើសទាំងអស់ដែលពាក់ព័ន្ធ — “— select all that apply” (`vendor_cat_hint`)
- [ ] រូបភាពបដា — “Banner photo” (`vendor_settings_banner`)
- [ ] — បង្ហាញពេញទទឹងនៅផ្នែកខាងលើហាង — “— displayed full-width at the top of your store page” (`vendor_settings_banner_hint`)
- [ ] ជំនួសបដា — “Replace banner” (`vendor_replace_banner`)
- [ ] ផ្ទុកឡើងបដា — “Upload banner” (`vendor_upload_banner`)
- [ ] JPG ឬ PNG អតិបរមា 4MB។ ទំហំណែនាំ៖ 1200×400px (ផ្តេក)។ រូបភាពត្រូវបានកាត់ជាបន្ទះទទឹង — រូបភាពខ្ពស់ ឬបញ្ឈរនឹងត្រូវកាត់ចេញនៅផ្នែកខាងលើ និងខាងក្រោម។ — “JPG or PNG, max 4MB. Recommended size: 1200×400px (landscape). The image is cropped to a wide strip — tall or portrait images will be cut off at the top and bottom.” (`vendor_banner_upload_hint`)
- [ ] រូបភាពវិចិត្រសាល — “Gallery photos” (`vendor_gallery`)
- [ ] — រហូតដល់ ១០ រូបភាព — “— up to 10 photos shown on your store page” (`vendor_settings_photos_hint`)
- [ ] បន្ថែមរូបភាព — “Add photo” (`vendor_settings_photos`)
- [ ] JPG ឬ PNG អតិបរមា 4MB។ ទំហំណែនាំ៖ 1200×675px (ផ្តេក 16:9)។ រូបភាពបញ្ឈរ ឬការេនឹងត្រូវកាត់នៅចំហៀង។ %s/10 បានប្រើ។ — “JPG or PNG, max 4MB. Recommended size: 1200×675px (landscape, 16:9). Portrait or square images will be cropped at the sides. %s/10 used.” (`vendor_gallery_upload_hint`)
- [ ] លេខ QR ធនាគារ — “Bank QR Code” (`vendor_settings_bank_qr`)
- [ ] អ្នកទិញស្កែននៅពេលបង់ប្រាក់។ — “Buyers scan this at checkout to pay for their order.” (`vendor_settings_bank_hint`)
- [ ] ជំនួស QR code — “Replace QR code” (`vendor_replace_qr`)
- [ ] ផ្ទុកឡើង QR code — “Upload QR code” (`vendor_upload_qr`)
- [ ] — JPG ឬ PNG អតិបរមា 2MB — “— JPG or PNG, max 2MB” (`vendor_qr_hint`)
- [ ] ផ្ទុកឡើង — “Upload” (`vendor_upload`)
- [ ] ពាក្យសម្ងាត់បច្ចុប្បន្ន — “Current password” (`settings_current_pw`)
- [ ] ពាក្យសម្ងាត់ថ្មី — “New password” (`settings_new_pw`)
- [ ] យ៉ាងហោចណាស់ ៨ តួអក្សរ។ — “At least 8 characters.” (`settings_pw_hint`)
- [ ] បញ្ជាក់ពាក្យសម្ងាត់ថ្មី — “Confirm new password” (`settings_confirm_pw`)
- [ ] ធ្វើបច្ចុប្បន្នភាពពាក្យសម្ងាត់ — “Update password” (`settings_update_pw`)
- [ ] ការលុបគណនីរបស់អ្នកក៏លុបអាជីវកម្ម និងផលិតផលពាក់ព័ន្ធទាំងអស់ផងដែរ។ វាមិនអាចធ្វើវិញបានទេ។ គណនីដែលមានកម្មង់មិនទាន់រួចមិនអាចលុបបានទេ។ — “Deleting your account also removes your business and all associated products. This cannot be undone. Accounts with open orders cannot be deleted.” (`vendor_delete_warning`)
- [ ] បញ្ជាក់ពាក្យសម្ងាត់ — “Confirm your password” (`settings_confirm_pw_label`)
- [ ] លុបគណនីរបស់ខ្ញុំ — “Delete my account” (`settings_delete_confirm`)

## Vendor products `/products/`

- [ ] ផលិតផល — “Products” (`vendor_products`)
- [ ] បណ្ណសារ — “Archive” (`prod_archive`)
- [ ] កូដបញ្ចុះតម្លៃ — “Coupons” (`vendor_coupons`)
- [ ] ប្រភេទ — “Category” (`search_category`)
- [ ] តម្លៃ — “Price” (`vendor_col_price`)
- [ ] បោះបង់ការបញ្ចុះតម្លៃ — “Cancel sale” (`prod_cancel_sale`)
- [ ] ប្រភេទ — “Variants” (`product_variants`)
- [ ] ស្តុក — “Stock” (`vendor_col_stock`)
- [ ] ការដឹកជញ្ជូន — “Delivery” (`order_delivery`)
- [ ] ការពិពណ៌នា — “Description” (`vendor_settings_description`)
- [ ] ស្ថានភាព — “Status” (`vendor_col_status`)
- [ ] សកម្ម — “Active” (`vendor_status_active`)
- [ ] អសកម្ម — “Inactive” (`vendor_status_inactive`)
- [ ] កែសម្រួល — “Edit” (`prod_edit`)
- [ ] បិទដំណើរការ — “Deactivate” (`prod_deactivate`)
- [ ] បើកដំណើរការ — “Activate” (`prod_activate`)
- [ ] លុប — “Delete” (`prod_delete`)
- [ ] មើលជាមុន ↗ — “Preview ↗” (`prod_preview`)
- [ ] អ្នកត្រូវការអាជីវកម្មដែលបានអនុម័តមុននឹងបន្ថែមផលិតផល។ — “You need an approved business before adding products.” (`prod_need_business`)
- [ ] អាជីវកម្ម — “Business” (`order_business`)
- [ ] ឈ្មោះផលិតផល — “Product name” (`prod_name`)
- [ ] តម្លៃ (ដុល្លារ) — “Price (USD)” (`search_price_usd`)
- [ ] កំណត់តាមប្រភេទខាងក្រោម — “Set per variant below” (`prod_stock_variant_hint`)
- [ ] វិធីដឹកជញ្ជូន — “Delivery method” (`prod_delivery_method`)
- [ ] តម្លៃបញ្ចុះ (ដុល្លារ) — “Sale price (USD)” (`prod_sale_price`)
- [ ] ស្រេចចិត្ត — “Optional” (`prod_optional`)
- [ ] ថ្ងៃបញ្ចប់ការបញ្ចុះតម្លៃ — “Sale end date” (`prod_sale_date`)
- [ ] ម៉ោងបញ្ចប់ការបញ្ចុះតម្លៃ — “Sale end time” (`prod_sale_time`)
- [ ] — ម៉ោង — — “— Time —” (`prod_time_placeholder`)
- [ ] ស្រេចចិត្ត — បន្ថែមប្រភេទសម្រាប់ជម្រើសនីមួយៗ (ឧ. ទំហំ ឬ ពណ៌) ដែលមានស្តុករៀងៗខ្លួន។ — “Optional — add a variant for each option (e.g. Size or Color) with its own stock.” (`prod_variants_hint`)
- [ ] + បន្ថែមប្រភេទ — “+ Add variant” (`prod_add_variant`)
- [ ] រូបភាព — “Photos” (`submit_photos`)
- [ ] jpg ឬ png · អតិបរមា 2MB ក្នុងមួយ · រហូតដល់ 9 · រូបភាពដំបូងក្លាយជារូបភាពមេ — “jpg or png · max 2MB each · up to 9 · first photo becomes the main image” (`prod_photos_hint`)
- [ ] បន្ថែមផលិតផល — “Add product” (`prod_add_product`)
- [ ] មេ — “Main” (`prod_main`)
- [ ] រក្សាទុកការផ្លាស់ប្តូរ — “Save changes” (`prod_save_changes`)
- [ ] ត្រង់ — “At” (`prod_payout_at`)
- [ ] % កម្រៃ → អ្នកទទួលបាន ~$ — “% fee → you receive ~$” (`prod_payout_mid`)
- [ ] — ជ្រើសរើសប្រភេទ — — “— Select a category —” (`prod_select_category`)
- [ ] — ជ្រើសរើសប្រភេទរង — — “— Select subcategory —” (`prod_select_subcategory`)
- [ ] សូមជ្រើសរើសប្រភេទ។ — “Please select a category.” (`prod_please_select_category`)
- [ ] ស្លាកប្រភេទ - ខ្មែរ — “ស្លាកប្រភេទ - ខ្មែរ” (`prod_variant_label_km`)
- [ ] ស្លាកប្រភេទ - អង់គ្លេស — “Variant Label - English” (`prod_variant_label_en`)
- [ ] ស្តុក — “Stock” (`prod_stock_word`)
- [ ] តម្លៃជំនួស (ស្រេចចិត្ត) — “Price override (optional)” (`prod_price_override_opt`)
- [ ] លុបប្រភេទផលិតផល — “Delete variant” (`prod_delete_variant`)
- [ ] គ្មានផលិតផលក្នុងបណ្ណសារ។ — “No archived products.” (`prod_no_archived`)
- [ ] រូបភាព — “Photo” (`prod_col_photo`)
- [ ] ឈ្មោះ — “Name” (`vendor_col_name`)
- [ ] ដកចេញពីបណ្ណសារ — “Unarchive” (`prod_unarchive`)
- [ ] កូដបញ្ចុះតម្លៃដែលអ្នកបង្កើត នឹងបញ្ចុះតម្លៃតែផលិតផលរបស់អ្នកប៉ុណ្ណោះ។ ចំនួនបញ្ចុះតម្លៃនឹងកាត់ចេញពីចំណូលរបស់អ្នក — teepsaa មិនចេញថ្លៃឲ្យទេ។ — “A coupon you create only discounts your own products. The discount comes out of your own payout — teepsaa doesn't cover it.” (`vendor_coupons_desc`)
- [ ] ដាក់ស្នើអាជីវកម្ម — “Submit a business” (`prod_submit_business`)
- [ ] កូដ — ឧ. SAVE10 — “Code — e.g. SAVE10” (`vendor_coupon_code_ph`)
- [ ] បញ្ចុះ % — “% off” (`vendor_coupon_type_percent`)
- [ ] បញ្ចុះ $ — “$ off” (`vendor_coupon_type_fixed`)
- [ ] តម្លៃ — “Value” (`vendor_coupon_value_ph`)
- [ ] កម្ម៉ង់អប្បបរមា — “Min order” (`vendor_coupon_min_order_ph`)
- [ ] ចំនួនប្រើប្រាស់អតិបរមា — “Max uses” (`vendor_coupon_max_uses_ph`)
- [ ] ចាប់ផ្តើម (ទទេ = ភ្លាមៗ) — “Starts (blank = immediately)” (`vendor_coupon_starts_title`)
- [ ] ផុតកំណត់ (ទទេ = មិនផុតកំណត់) — “Expires (blank = never)” (`vendor_coupon_expires_title`)
- [ ] បង្កើតកូដបញ្ចុះតម្លៃ — “Create coupon” (`vendor_coupon_create`)
- [ ] មិនទាន់មានកូដបញ្ចុះតម្លៃទេ។ — “No coupons yet.” (`vendor_coupon_none`)
- [ ] កូដ — “Code” (`vendor_coupon_col_code`)
- [ ] បញ្ចុះតម្លៃ — “Discount” (`vendor_coupon_col_discount`)
- [ ] កម្ម៉ង់អប្បបរមា — “Min Order” (`vendor_coupon_col_min_order`)
- [ ] ប្រើប្រាស់អតិបរមា — “Max Uses” (`vendor_coupon_col_max_uses`)
- [ ] បានប្រើ — “Uses” (`vendor_coupon_col_uses`)
- [ ] ចាប់ផ្តើម — “Starts” (`vendor_coupon_col_starts`)
- [ ] ផុតកំណត់ — “Expires” (`vendor_coupon_col_expires`)
- [ ] ស្ថានភាព — “Status” (`vendor_coupon_col_status`)
- [ ] ផុតកំណត់ — “Expired” (`vendor_coupon_expired`)
- [ ] លុបកូដបញ្ចុះតម្លៃនេះ? — “Delete this coupon?” (`vendor_coupon_delete_confirm`)
- [ ] កំពុងដំណើរការ — “Active” (`vendor_coupon_active`)
- [ ] បិទដំណើរការ — “Inactive” (`vendor_coupon_inactive`)
- [ ] រក្សាទុក — “Save” (`product_save`)
- [ ] ផលិតផលរបស់ខ្ញុំ — “My Products” (`prod_my_products`)
- [ ] មិនទាន់មានផលិតផលទេ។ — “No products yet.” (`vendor_no_products`)
- [ ] បន្ថែមផលិតផលដំបូងរបស់អ្នក — “Add your first product” (`vendor_add_product`)
- [ ] ការវាយតម្លៃ — “Rating” (`prod_col_rating`)
- [ ] ប្រភេទ — “variants” (`prod_variants_suffix`)

## Vendor orders `/orders-vendor/`

- [ ] ការកម្មង់ — “Orders” (`vendor_orders`)
- [ ] សំណង — “Refunds” (`vendor_refunds`)
- [ ] សំណង — “Refund” (`vorder_refund_word`)
- [ ] ព័ត៌មានកម្មង់ — “Order info” (`order_info`)
- [ ] កាលបរិច្ឆេទ — “Date” (`order_date`)
- [ ] អតិថិជន — “Customer” (`vorder_customer`)
- [ ] អាជីវកម្ម — “Business” (`order_business`)
- [ ] ទំនិញ — “Items” (`order_items`)
- [ ] ផលិតផល — “Product” (`order_col_product`)
- [ ] ចំនួន — “Qty” (`order_col_qty`)
- [ ] តម្លៃ — “Price” (`order_col_price`)
- [ ] សរុប — “Total” (`order_col_total`)
- [ ] សរុបរង — “Subtotal” (`checkout_subtotal`)
- [ ] បានអនុវត្តកូដ៖ — “Code applied:” (`checkout_coupon_applied`)
- [ ] ដឹកជញ្ជូន (មិនអាចសងវិញ) — “Delivery (non-refundable)” (`vorder_delivery_nonrefund`)
- [ ] សងប្រាក់ទៅអ្នកទិញ — “Refund to buyer” (`vorder_refund_to_buyer`)
- [ ] មូលហេតុរបស់អ្នកទិញ — “Buyer's reason” (`vorder_buyer_reason`)
- [ ] ស្ថានភាពសំណង — “Refund status” (`vorder_refund_status`)
- [ ] ការដឹកជញ្ជូនត្រឡប់ — “Return delivery” (`vorder_return_delivery`)
- [ ] តំណ Grab — “Grab link” (`vorder_grab_link`)
- [ ] តាមដានការប្រគល់វិញ ↗ — “Track return ↗” (`vorder_track_return`)
- [ ] ចុចខាងក្រោមនៅពេលអ្នកបានទទួលទំនិញត្រឡប់វិញ។ — “Click below once you have received the item back.” (`vorder_received_hint`)
- [ ] បញ្ជាក់ថាបានទទួលទំនិញ — “Confirm item received” (`vorder_confirm_received`)
- [ ] មិនទាន់មានការកម្មង់ទេ។ — “No orders yet.” (`vendor_no_orders`)
- [ ] គ្មានកម្មង់សំណងទេ។ — “No refund orders.” (`vendor_no_refunds`)
- [ ] លេខទូរស័ព្ទ — “Phone” (`settings_phone`)
- [ ] អាសយដ្ឋានដឹកជញ្ជូន — “Delivery address” (`settings_delivery_address`)
- [ ] អាសយដ្ឋាន Grab — “Grab address” (`vorder_grab_address`)
- [ ] ជាន់ / បន្ទប់ — “Floor / Unit” (`vorder_floor_unit`)
- [ ] កំណត់ចំណាំដឹកជញ្ជូន — “Delivery note” (`vorder_delivery_note`)
- [ ] អ្នកទិញមិនបានកំណត់អាសយដ្ឋានទេ។ — “No address set by buyer.” (`vorder_no_address`)
- [ ] ការដឹកជញ្ជូន — “Delivery” (`order_delivery`)
- [ ] សរុប — “Total” (`checkout_total`)
- [ ] កម្រៃជើងសារ — “Royalty fee” (`vorder_royalty_fee`)
- [ ] សំណងថ្លៃដឹកជញ្ជូន — “Delivery reimbursement” (`vorder_delivery_reimburse`)
- [ ] ទុនបម្រុងដឹកជញ្ជូន — “Delivery buffer” (`vorder_delivery_buffer`)
- [ ] ប្រាក់ទទួលបានរបស់អ្នក — “Your payout” (`vorder_your_payout`)
- [ ] តំណតាមដាន — “Tracking link” (`vorder_tracking_link`)
- [ ] មើលការតាមដាន ↗ — “View tracking ↗” (`vorder_view_tracking`)
- [ ] ស្ថានភាព — “Status” (`order_status_heading`)
- [ ] ដឹកជញ្ជូន — “Dispatch” (`vorder_dispatch`)
- [ ] <strong>កុំប្រើការបង់ប្រាក់ពេលដឹកជញ្ជូន (COD)</strong> នៅពេលកក់ការដឹកជញ្ជូន Grab។ អ្នកទិញបានបង់ប្រាក់ teepsaa រួចហើយ។ ប្រសិនបើអ្នកបើក COD អ្នកទិញនឹងត្រូវគិតប្រាក់ម្តងទៀតដោយអ្នកបើកបរ។ វានឹងបណ្តាលឱ្យហាមឃាត់ភ្លាមៗពីវេទិកា។ — “<strong>Do not use Cash on Delivery (COD)</strong> when booking your Grab delivery. The buyer has already paid teepsaa. If you enable COD, the buyer will be charged a second time by the driver. This will result in an immediate ban from the platform.” (`vorder_cod_warning`)
- [ ] កក់ការដឹកជញ្ជូននៅក្នុង Grab បន្ទាប់មកបិទភ្ជាប់តំណតាមដានខាងក្រោម។ — “Book the delivery in Grab, then paste the tracking link below.” (`vorder_dispatch_hint`)
- [ ] បិទភ្ជាប់តំណតាមដាន Grab… — “Paste Grab tracking URL…” (`vorder_tracking_placeholder`)
- [ ] សម្គាល់ថាបានដឹកជញ្ជូន — “Mark dispatched” (`vorder_mark_dispatched`)

## Add product `/submit/`

- [ ] ដាក់ស្នើអាជីវកម្ម — “Submit a Business” (`submit_title`)
- [ ] គណនីរបស់អ្នកមានអាជីវកម្មរួចហើយ។ គណនីអ្នកលក់នីមួយៗមានហាងតែមួយប៉ុណ្ណោះ។ — “Your account already has a business. Each vendor account is limited to one shop.” (`submit_has_business`)
- [ ] ត្រឡប់ទៅផ្ទាំងគ្រប់គ្រង — “Back to dashboard” (`submit_back_dashboard`)
- [ ] ឈ្មោះអាជីវកម្ម — “Business name” (`vendor_settings_biz_name`)
- [ ] ការពិពណ៌នា — “Description” (`vendor_settings_description`)
- [ ] ប្រភេទ — “Category” (`submit_category`)
- [ ] អ្នកអាចបន្ថែមប្រភេទបន្ថែមនៅពេលដាក់លក់ផលិតផល — “You can add more categories when you list products” (`submit_category_hint`)
- [ ] លេខផ្ទះ / ល្វែង — “House / Unit #” (`settings_address_house`)
- [ ] ផ្លូវ — “Street” (`settings_street`)
- [ ] ខណ្ឌ — “Khan” (`settings_address_khan`)
- [ ] ជ្រើសរើសខណ្ឌ — “Select Khan” (`settings_select_khan`)
- [ ] សង្កាត់ — “Sangkat” (`settings_address_sangkat`)
- [ ] ជ្រើសរើសសង្កាត់ — “Select Sangkat” (`settings_select_sangkat`)
- [ ] ទីតាំង — “Location” (`submit_location`)
- [ ] ចុចលើផែនទីដើម្បីខ្ទាស់អាជីវកម្មរបស់អ្នក — “Click the map to pin your business” (`submit_location_hint`)
- [ ] មិនទាន់ជ្រើសរើសទីតាំង — “No location selected” (`submit_no_location`)
- [ ] រូបភាព — “Photos” (`submit_photos`)
- [ ] រហូតដល់ 5 — jpg ឬ png អតិបរមា 2MB ក្នុងមួយ — “Up to 5 — jpg or png, max 2MB each” (`submit_photos_hint`)
- [ ] ដាក់ស្នើសម្រាប់ការត្រួតពិនិត្យ — “Submit for review” (`submit_for_review`)

## Buyer messages `/messages-buyer/`

- [ ] ជំនួយ — “Support” (`messages_title`)
- [ ] មើលសំណើដែលកំពុងរង់ចាំ — “View pending request” (`messages_pending`)
- [ ] ទាក់ទងជំនួយ — “Contact Support” (`messages_contact`)
- [ ] មិនទាន់មានសារទេ។ — “No messages yet.” (`messages_empty`)
- [ ] កំពុងរង់ចាំ — “Pending” (`messages_status_pending`)
- [ ] បានដាក់ស្នើ — “Submitted” (`messages_submitted`)
- [ ] ជំនួយ teepsaa — “teepsaa Support” (`messages_support_name`)
- [ ] អ្នក — “You” (`messages_you`)
- [ ] សំណើរបស់អ្នកកំពុងត្រូវបានពិនិត្យ។ ក្រុមការងាររបស់យើងនឹងឆ្លើយតបឱ្យបានឆាប់ — ជាធម្មតាក្នុងរយៈពេលមួយថ្ងៃធ្វើការ។ — “Your request is under review. Our team will respond as soon as possible — typically within one business day.” (`messages_pending_notice`)
- [ ] ផ្ញើសារទៅ ជំនួយ teepsaa… — “Message teepsaa Support…” (`messages_reply_placeholder`)

## Vendor messages `/messages-vendor/`

- [ ] ជំនួយ — “Support” (`messages_title`)
- [ ] មើលសំណើដែលកំពុងរង់ចាំ — “View pending request” (`messages_pending`)
- [ ] ទាក់ទងជំនួយ — “Contact Support” (`messages_contact`)
- [ ] មិនទាន់មានសារទេ។ — “No messages yet.” (`messages_empty`)
- [ ] កំពុងរង់ចាំ — “Pending” (`messages_status_pending`)
- [ ] បានដាក់ស្នើ — “Submitted” (`messages_submitted`)
- [ ] ជំនួយ teepsaa — “teepsaa Support” (`messages_support_name`)
- [ ] អ្នក — “You” (`messages_you`)
- [ ] សំណើរបស់អ្នកកំពុងត្រូវបានពិនិត្យ។ ក្រុមការងាររបស់យើងនឹងឆ្លើយតបឱ្យបានឆាប់ — ជាធម្មតាក្នុងរយៈពេលមួយថ្ងៃធ្វើការ។ — “Your request is under review. Our team will respond as soon as possible — typically within one business day.” (`messages_pending_notice`)
- [ ] ផ្ញើសារទៅ ជំនួយ teepsaa… — “Message teepsaa Support…” (`messages_reply_placeholder`)
- [ ] ការសន្ទនានេះត្រូវបានបិទ។ ប្រសិនបើអ្នកត្រូវការជំនួយបន្ថែម សូម%s។ — “This conversation is closed. If you need further help, %s.” (`messages_closed_notice`)
- [ ] ទាក់ទងជំនួយ — “contact support” (`messages_contact_lower`)

## Reviews `/review/`

- [ ] ត្រឡប់ទៅកម្មង់ — “Back to order” (`review_back_order`)
- [ ] ទុកមតិយោបល់ — “Leave a review” (`review_title`)
- [ ] ចុចផ្កាយដើម្បីវាយតម្លៃ — “Tap a star to rate” (`review_tap_star`)
- [ ] មតិយោបល់ស្រេចចិត្ត… — “Optional comment…” (`review_comment_ph`)
- [ ] ដាក់ស្នើមតិយោបល់ — “Submit review” (`review_submit`)
- [ ] អាក្រក់ណាស់ — “Terrible” (`review_star_1`)
- [ ] មិនល្អ — “Poor” (`review_star_2`)
- [ ] មធ្យម — “Average” (`review_star_3`)
- [ ] ល្អ — “Good” (`review_star_4`)
- [ ] ល្អឥតខ្ចោះ — “Excellent” (`review_star_5`)

## Order status labels (buyer + vendor order pages)

- [ ] ការបង់ប្រាក់<br>បានដាក់ស្នើ — “Payment<br>submitted” (`ostatus_pending`)
- [ ] ការបង់ប្រាក់<br>បានបញ្ជាក់ — “Payment<br>confirmed” (`ostatus_paid`)
- [ ] បានដឹកជញ្ជូន — “Dispatched” (`ostatus_dispatched`)
- [ ] បានប្រគល់ — “Delivered” (`ostatus_delivered`)
- [ ] បានបញ្ចប់ — “Completed” (`ostatus_completed`)
- [ ] ការកម្មង់ត្រូវបានលុបចោល — “Order cancelled” (`ostatus_cancelled`)

## Refund status labels (refund pages)

- [ ] ស្នើសុំ<br>សំណង — “Refund<br>Requested” (`rstatus_requested`)
- [ ] យល់ព្រម<br>ប្រគល់វិញ — “Return<br>Approved” (`rstatus_approved`)
- [ ] ប្រគល់វិញ<br>បានផ្ញើ — “Return<br>Sent” (`rstatus_dispatched`)
- [ ] បានទទួល<br>ទំនិញ — “Item<br>Received” (`rstatus_received`)
- [ ] បានសងវិញ — “Refunded” (`rstatus_refunded`)
- [ ] សំណងត្រូវបានបដិសេធ — “Refund rejected” (`rstatus_rejected`)

## Contact pages `/contact/`, `/contact-buyer/`, `/contact-vendor/`

- [ ] ទំនាក់ទំនងយើង — “Contact Us” (`contact_title`)
- [ ] មានសំណួរ? សូមផ្ញើសារមកយើង ហើយយើងនឹងឆ្លើយតបទៅអ្នកវិញ។ — “Have a question? Send us a message and we'll get back to you.” (`contact_lead`)
- [ ] មានគណនីរួចហើយ? %s ដើម្បីទាក់ទងជំនួយជាមួយព័ត៌មានលម្អិតកម្មង់របស់អ្នក ឬ %s សម្រាប់ចម្លើយរហ័ស។ — “Already have an account? %s to contact support with your order details, or %s for quick answers.” (`contact_signin_note`)
- [ ] ចូលគណនី — “Sign in” (`footer_sign_in`)
- [ ] ពិនិត្យមជ្ឈមណ្ឌលជំនួយរបស់យើង — “check our Help Center” (`contact_help_link`)
- [ ] ឈ្មោះរបស់អ្នក — “Your name” (`contact_name`)
- [ ] ឈ្មោះពេញ — “Full name” (`register_name`)
- [ ] អាសយដ្ឋានអ៊ីមែល — “Email address” (`contact_email`)
- [ ] ប្រធានបទ — “Subject” (`contact_subject`)
- [ ] សេចក្តីសង្ខេបខ្លីនៃសំណួររបស់អ្នក — “Brief summary of your question” (`contact_subject_ph_generic`)
- [ ] សារ — “Message” (`contact_message`)
- [ ] តើយើងអាចជួយអ្វីបាន? — “How can we help?” (`contact_message_ph_generic`)
- [ ] អតិបរមា ២០០០ តួអក្សរ — “Max 2000 characters” (`contact_max_chars`)
- [ ] ផ្ញើសារ — “Send message” (`contact_send`)
- [ ] ទាក់ទងជំនួយ — “Contact Support” (`messages_contact`)
- [ ] បំពេញព័ត៌មានលម្អិតខាងក្រោម ហើយក្រុមការងាររបស់យើងនឹងឆ្លើយតបទៅអ្នកវិញ។ — “Fill in the details below and our team will get back to you.” (`contact_support_lead`)
- [ ] អ្នកមានសំណើកំពុងរង់ចាំការត្រួតពិនិត្យរួចហើយ។ សូមរង់ចាំក្រុមការងាររបស់យើងឆ្លើយតបមុននឹងដាក់ស្នើម្តងទៀត។ — “You already have a request pending review. Please wait for our team to respond before submitting another.” (`contact_pending`)
- [ ] មើលសាររបស់អ្នក → — “View your messages →” (`contact_view_messages`)
- [ ] ប្រភេទបញ្ហា — “Issue type” (`contact_issue_type`)
- [ ] — ជ្រើសរើសបញ្ហា — — “— Select an issue —” (`contact_select_issue`)
- [ ] បញ្ហាកម្មង់ — “Order issue” (`contact_issue_order`)
- [ ] បញ្ហាការបង់ប្រាក់ — “Payment issue” (`contact_issue_payment`)
- [ ] បញ្ហាគណនី — “Account issue” (`contact_issue_account`)
- [ ] ផ្សេងទៀត — “Other” (`contact_issue_other`)
- [ ] កម្មង់ពាក់ព័ន្ធ — “Related order” (`contact_related_order`)
- [ ] (ស្រេចចិត្ត) — “(optional)” (`form_optional`)
- [ ] — គ្មានកម្មង់ជាក់លាក់ — — “— No specific order —” (`contact_no_order`)
- [ ] សេចក្តីសង្ខេបខ្លីនៃបញ្ហារបស់អ្នក — “Brief summary of your issue” (`contact_subject_ph`)
- [ ] ពិពណ៌នាបញ្ហារបស់អ្នកឱ្យលម្អិត… — “Describe your issue in detail…” (`contact_message_ph`)
- [ ] ដាក់ស្នើសំណើ — “Submit request” (`contact_submit`)
- [ ] បោះបង់ — “Cancel” (`btn_cancel`)
- [ ] វិវាទកម្មង់ — “Order dispute” (`contact_issue_dispute`)
- [ ] បញ្ហាការទូទាត់ — “Payout issue” (`contact_issue_payout`)
- [ ] បញ្ហាផលិតផល/បញ្ជី — “Product/listing issue” (`contact_issue_listing`)

## Careers `/careers/`

- [ ] ពេញម៉ោង — “Full-time” (`emp_full_time`)
- [ ] ក្រៅម៉ោង — “Part-time” (`emp_part_time`)
- [ ] កិច្ចសន្យា — “Contract” (`emp_contract`)
- [ ] កម្មសិក្សា — “Internship” (`emp_internship`)
- [ ] ការងារឯករាជ្យ — “Freelance” (`emp_freelance`)
- [ ] ការងារ — “Careers” (`careers_title`)
- [ ] យើងកំពុងកសាងទីផ្សារឈានមុខសម្រាប់ភ្នំពេញ។ ប្រសិនបើអ្នកចង់ចូលរួម យើងរីករាយចង់ស្តាប់ពីអ្នក។ — “We're building the go-to marketplace for Phnom Penh. If you'd like to be part of it, we'd love to hear from you.” (`careers_lead`)
- [ ] គ្មានមុខតំណែងបើកចំហនៅពេលនេះទេ។ សូមពិនិត្យមើលឡើងវិញឆាប់ៗ។ — “No open positions at this time. Check back soon.” (`careers_empty`)
- [ ] ដាក់ពាក្យសម្រាប់តំណែងនេះ — “Apply for this role” (`careers_apply`)
- [ ] មុខតំណែងមិនអាចរកបាន — “Position unavailable” (`apply_unavailable_title`)
- [ ] មុខតំណែងនេះលែងបើកទៀតហើយ។ %s។ — “This position is no longer open. %s.” (`apply_unavailable_body`)
- [ ] មើលតំណែងបើកចំហទាំងអស់ — “See all open roles” (`apply_see_all`)
- [ ] បានទទួលពាក្យសុំ — “Application received” (`apply_received_title`)
- [ ] អរគុណសម្រាប់ការដាក់ពាក្យសម្រាប់ %s។ យើងបានទទួលពាក្យសុំរបស់អ្នក ហើយនឹងទាក់ទងទៅវិញ ប្រសិនបើមានភាពសមស្រប។ — “Thanks for applying to %s. We've received your application and will be in touch if there's a fit.” (`apply_received_body`)
- [ ] ត្រឡប់ទៅការងារ — “Back to careers” (`apply_back_careers`)
- [ ] តំណែងទាំងអស់ — “All roles” (`apply_all_roles`)
- [ ] ដាក់ពាក្យ៖ — “Apply:” (`apply_prefix`)
- [ ] ឈ្មោះពេញ — “Full name” (`register_name`)
- [ ] អ៊ីមែល — “Email” (`login_email`)
- [ ] ទូរស័ព្ទ — “Phone” (`apply_phone`)
- [ ] (ស្រេចចិត្ត) — “(optional)” (`form_optional`)
- [ ] ហេតុអ្វីអ្នកសមស្រប — “Why you're a fit” (`apply_why_fit`)
- [ ] ប្រវត្តិរូបសង្ខេប — “Résumé” (`apply_resume`)
- [ ] (ស្រេចចិត្ត — PDF, DOC, ឬ DOCX, អតិបរមា ៥ MB) — “(optional — PDF, DOC, or DOCX, max 5 MB)” (`apply_resume_hint`)
- [ ] ដាក់ស្នើពាក្យសុំ — “Submit application” (`apply_submit`)

## Notifications dropdown (header bell)

- [ ] ទើបតែឥឡូវ — “just now” (`notif_just_now`)
- [ ] %s នាទីមុន — “%sm ago” (`notif_min_ago`)
- [ ] %s ម៉ោងមុន — “%sh ago” (`notif_hour_ago`)
- [ ] %s ថ្ងៃមុន — “%sd ago” (`notif_day_ago`)

## Other pages (about, help, terms, privacy, shipping, returns)

- [ ] អំពីទីផ្សារ — “About teepsaa” (`about_title`)
- [ ] ទីផ្សារ គឺជាទីផ្សារក្នុងស្រុកដែលភ្ជាប់អ្នកទិញ និងអ្នកលក់នៅទូទាំងភ្នំពេញ — បង្កើតឡើងដើម្បីធ្វើឱ្យការទិញ និងលក់តាមអ៊ីនធឺណិតមានភាពងាយស្រួល រួសរាយ និងគួរឱ្យទុកចិត្តសម្រាប់ប្រជាជនកម្ពុជាទាំងអស់។ — “teepsaa is a local marketplace connecting buyers and vendors across Phnom Penh — built to make buying and selling online simple, friendly, and trustworthy for everyone in Cambodia.” (`about_lead`)
- [ ] បេសកកម្មរបស់យើង — “Our mission” (`about_mission_h`)
- [ ] យើងចង់ឱ្យការទិញទំនិញមានភាពងាយស្រួល។ ទីផ្សារ ផ្តល់ឱ្យអ្នកលក់ក្នុងស្រុកតូចៗនូវកន្លែងសម្រាប់ទាក់ទងអតិថិជនតាមអ៊ីនធឺណិត ហើយផ្តល់ឱ្យអ្នកទិញនូវកន្លែងតែមួយដែលអាចទុកចិត្តបានដើម្បីស្វែងរក និងកម្មង់ — ដោយគ្មានភាពរញ៉េរញ៉ៃ។ — “We want shopping made easy. teepsaa gives small local vendors a place to reach customers online, and gives buyers a single, dependable place to discover and order from them — without the hassle.” (`about_mission_p`)
- [ ] សម្រាប់អ្នកទិញ — “For buyers” (`about_buyers_h`)
- [ ] រកមើលផលិតផលពីអ្នកលក់ក្នុងស្រុក រក្សាទុកអ្វីដែលអ្នកចូលចិត្តទៅក្នុងបញ្ជីចង់បាន ហើយបង់ប្រាក់ក្នុងការចុចពីរបីដង។ តម្លៃបង្ហាញជាដុល្លារអាមេរិក ឬរៀលខ្មែរ ហើយកម្មង់ត្រូវបានដឹកជញ្ជូនក្នុងស្រុក ដូច្នេះទំនិញរបស់អ្នកមកដល់យ៉ាងឆាប់រហ័ស។ — “Browse products from local vendors, save what you love to your wishlist, and check out in a few taps. Prices show in US Dollars or Khmer Riel, and orders are delivered locally so your purchases reach you quickly.” (`about_buyers_p`)
- [ ] សម្រាប់អ្នកលក់ — “For vendors” (`about_vendors_h`)
- [ ] បើកហាង ដាក់លក់ផលិតផលរបស់អ្នក ហើយចាប់ផ្តើមទាក់ទងអ្នកទិញក្នុងស្រុក — ដោយមិនចាំបាច់មានហាងជាក់ស្តែង។ គ្រប់គ្រងកម្មង់ ផ្ញើសារទៅអតិថិជន និងតាមដានប្រាក់ចំណូលពីផ្ទាំងគ្រប់គ្រងតែមួយដ៏សាមញ្ញ។ — “Open a shop, list your products, and start reaching local buyers — no storefront required. Manage orders, message customers, and track payouts from one simple dashboard.” (`about_vendors_p`)
- [ ] លក់នៅទីផ្សារ — “Sell on teepsaa” (`footer_sell_on`)
- [ ] បង្កើតឡើងសម្រាប់កម្ពុជា — “Built for Cambodia” (`about_cambodia_h`)
- [ ] ទីផ្សារ ដំណើរការតាមរបៀបដែលភ្នំពេញទិញទំនិញ៖ ពេញលេញជាភាសាខ្មែរ និងអង់គ្លេស ជាមួយការដឹកជញ្ជូនក្នុងស្រុក និងវិធីបង់ប្រាក់ដែលមនុស្សប្រើប្រាស់រួចហើយ។ យើងជាក្រុមការងារក្នុងស្រុកដែលកំពុងបង្កើតសម្រាប់សហគមន៍របស់យើងផ្ទាល់ ហើយយើងទើបតែចាប់ផ្តើម។ — “teepsaa works the way Phnom Penh shops: fully in Khmer and English, with local delivery and the payment methods people already use. We're a local team building for our own community, and we're just getting started.” (`about_cambodia_p`)
- [ ] ចាប់ផ្តើមទិញទំនិញ — “Start shopping” (`about_cta_shop`)
- [ ] យើងកំពុងជ្រើសរើសបុគ្គលិក — “We're hiring” (`about_cta_hiring`)
- [ ] មជ្ឈមណ្ឌលជំនួយ — “Help Center” (`footer_help_center`)
- [ ] ទាក់ទងជំនួយ — “Contact Support” (`messages_contact`)


## Hardcoded Khmer (baked into page files, not in lang/km.php)

These strings live directly in the listed file. Corrections here are edited
in the file itself, not in lang/km.php.

### footer/footer.php
- [ ] ទិញឱ្យងាយស្រួល
- [ ] ខ្មែរ

### products/index.php
- [ ] ខ្មែរ
- [ ] ឈ្មោះផលិតផលជាភាសាខ្មែរ
- [ ] ការពិពណ៌នាជាភាសាខ្មែរ

### privacy/index.php
- [ ] គោលការណ៍ភាពឯកជន
- [ ] មាតិកាមិនអាចប្រើប្រាស់បានទេនាពេលនេះ។

### config/notify.php
- [ ] នេះជាសារស្វ័យប្រវត្តិពីទីផ្សារ។ សូមកុំឆ្លើយតប។

### config/i18n.php
- [ ] មករា
- [ ] កុម្ភៈ
- [ ] មីនា
- [ ] មេសា
- [ ] មិថុនា
- [ ] កក្កដា
- [ ] សីហា
- [ ] កញ្ញា
- [ ] តុលា
- [ ] វិច្ឆិកា
- [ ] ធ្នូ
- [ ] ឧសភា
- (+9 more)

### config/email-templates.php
- [ ] បានទទួលការបញ្ជាទិញ
- [ ] ទីផ្សារ
- [ ] យើងបានទទួលការបញ្ជាទិញរបស់អ្នក
- [ ] យើងនឹងផ្ទៀងផ្ទាត់ការបង់ប្រាក់
- [ ] របស់អ្នក ហើយបញ្ជាក់ការបញ្ជាទិញក្នុងរយៈពេល ១ ម៉ោង។ អ្នកនឹងទទួលអ៊ីមែលមួយទៀតនៅពេលបញ្ជាក់រួច។
- [ ] មើលការបញ្ជាទិញរបស់ខ្ញុំ
- [ ] ការបង់ប្រាក់របស់អ្នកត្រូវបានបញ្ជាក់
- [ ] ការបង់ប្រាក់ត្រូវបានបញ្ជាក់
- [ ] ជម្រាបសួរ
- [ ] ការបង់ប្រាក់សម្រាប់ការបញ្ជាទិញ
- [ ] ត្រូវបានបញ្ជាក់។ អ្នកលក់កំពុងរៀបចំការបញ្ជាទិញរបស់អ្នក។
- [ ] មើលការបញ្ជាទិញ
- (+38 more)

### terms/index.php
- [ ] លក្ខខណ្ឌប្រើប្រាស់
- [ ] មាតិកាមិនអាចប្រើប្រាស់បានទេនាពេលនេះ។

### admin/messages/email-edit.php
- [ ] សរុប
- [ ] ខ្មែរ

### admin/banners.php
- [ ] ខ្មែរ
- [ ] ចំណងជើងជាភាសាខ្មែរ
- [ ] អក្សររងជាភាសាខ្មែរ

### admin/content.php
- [ ] ខ្មែរ

### admin/faq.php
- [ ] ខ្មែរ

### admin/categories.php
- [ ] ខ្មែរ

### admin/careers.php
- [ ] ចំណងជើងការងារជាភាសាខ្មែរ
- [ ] ទីតាំងជាភាសាខ្មែរ
- [ ] ការពិពណ៌នាការងារជាភាសាខ្មែរ

### dashboard-vendor/settings/index.php
- [ ] ឈ្មោះហាងជាភាសាខ្មែរ
- [ ] ការពិពណ៌នាហាងជាភាសាខ្មែរ

### shipping/index.php
- [ ] ការដឹកជញ្ជូន
- [ ] មាតិកាមិនអាចប្រើប្រាស់បានទេនាពេលនេះ។

### checkout/confirm.php
- [ ] កំណត់ចំណាំដឹកជញ្ជូន
- [ ] សរុប

### returns/index.php
- [ ] ការប្រគល់ទំនិញ
- [ ] មាតិកាមិនអាចប្រើប្រាស់បានទេនាពេលនេះ។

### header/header.php
- [ ] ខ្មែរ

### help/index.php
- [ ] រកចម្លើយចំពោះសំណួរទូទៅខាងក្រោម។
- [ ] នៅតែត្រូវការជំនួយ
- [ ] ប្រសិនបើអ្នករកមិនឃើញអ្វីដែលអ្នកកំពុងស្វែងរក ក្រុមការងារជំនួយរបស់យើងនៅទីនេះ។

### cron/review-reminder.php
- [ ] អ្នក

### cron/abandoned-cart.php
- [ ] អ្នក


## Khmer stored in the database (verify inside the admin panel)

- [ ] Category names (Admin → Categories — every category has a Khmer name)
- [ ] Banner titles + subtitles (Admin → Marketing → Banners)
- [ ] Content pages, Khmer versions: About, Help/FAQ, Terms, Privacy,
      Shipping, Returns (Admin → Content)
- [ ] FAQ questions and answers (Admin → FAQ)
- [ ] Job listings (Admin → Careers)
- [ ] Email templates — every template has a Khmer block; send yourself a
      test of each (Admin → Messages → Emails). Includes: verification code,
      password reset, order received/confirmed/dispatched/delivered, refund
      emails, review reminder, abandoned cart, low stock
- [ ] Filler product names + descriptions in Khmer (your own seeded content)
- [ ] Filler business names + descriptions in Khmer

## After corrections

- [ ] Claude applies all noted fixes to lang/km.php / files / DB
- [ ] Re-check the corrected strings on the live pages in Khmer mode
- [ ] Verify Khmer renders correctly on a real phone (Khmer script line
      breaking is inconsistent across devices — check nothing overlaps)
