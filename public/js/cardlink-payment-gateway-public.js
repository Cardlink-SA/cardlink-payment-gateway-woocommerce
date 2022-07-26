(function ($) {
	'use strict';

	function clickPaymentCard() {
		$("body").on("click", ".payment-cards input", function () {
			var $newCard = $(".payment-cards-new-card");
			if ($(this).val() === "new") {
				$newCard.show();
			} else {
				$newCard.hide();
				$newCard.find("input").prop("checked", false);
			}
		});
	}

	function deletePaymentCard() {
		$("body").on("click", ".payment-cards .remove", function (e) {
			e.preventDefault();
			var selected_card_id = $(this).parent().children('input').attr('id');
			var selected_card_value = $(this).parent().children('input').val();
			updatePaymentCards(selected_card_id, selected_card_value);
		});
	}

	function updatePaymentCards(selected_card_id, selected_card_value) {
		var $wrapper = $(".payment-cards__tokens");
		var $params = {
			selected_card_id: selected_card_id,
			selected_card_value: selected_card_value,
		};
		$.ajax({
			url: window.urls.ajax,
			data: {
				action: 'delete_token',
				params: $params
			},
			type: 'post',
			dataType: 'json',
			beforeSend: function () {
				$wrapper.addClass('loading');
			},
			success: function (data) {
				if (data.status === 'success') {
					$wrapper.html(data.response.payment_cards_html);
				}
			},
			error: function (error) {
				console.error(error);
			},
			complete: function () {
				$wrapper.removeClass('loading');
			}
		});
	}

	function getOrderStatus(orderId) {
		return $.ajax({
			url: window.urls.ajax,
			data: {
				action: 'get_order_status',
				order_id: orderId
			},
			type: 'post',
			dataType: 'json',
			success: function (data) {
				if (data.status && data.response) {
					console.log('redirecting...', data.response);
					// window.location.href = data.response;
					return true;
				}
				return false;
			},
			error: function (error) {
				return false;
			}
		});
	}

	function set_redirection_status(orderId) {
		return new Promise((resolve, reject) => {
			$.ajax({
				url: window.urls.ajax,
				data: {
					action: 'set_redirection_status',
					order_id: orderId
				},
				type: 'post',
				dataType: 'json',
				success: function (data) {
					resolve(data);
				},
				error: function (error) {
					reject();
				}
			});
		});
	}

	function check_order_status(orderId) {
		var polling = setInterval(function () {
			$.ajax({
				url: window.urls.ajax,
				data: {
					action: 'check_order_status',
					order_id: orderId
				},
				type: 'post',
				dataType: 'json',
				success: function (data) {
					if (data.status) {
						var redirectUrl = data.response.redirect_url;
						var redirected = data.response.redirected;
						if (!redirected && redirectUrl) {
							clearInterval(polling);
							window.location.href = redirectUrl;
						}
					}
				},
				error: function (error) {
					clearInterval(polling);
					window.location.reload();
				}
			});
		}, 1000);
	}

	function modalPayment($iframe) {
		var orderId = $iframe.data('order-id');
		set_redirection_status(orderId)
			.then((data) => {
				if (data.status) {
					check_order_status(orderId);
				}
			})
			.catch((error) => {
				console.log(error);
			});
	}

	$(document).ready(function () {
		clickPaymentCard();
		deletePaymentCard();
		var $iframe = $('#payment_iframe');
		if ($iframe.length > 0) {
			modalPayment($iframe);
		}
	});

})(jQuery);
