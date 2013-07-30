function order_items_addloadevent(){
	jQuery("#lumpy_order_list").sortable({
		placeholder: "sortable-placeholder",
		revert: false,
		tolerance: "pointer"
	});
}

addLoadEvent(order_items_addloadevent);

function orderItems() {
	jQuery("#update_text").html("Updating Order...");
	jQuery("#hdn_order_items").val(jQuery("#lumpy_order_list").sortable("toArray"));
}
