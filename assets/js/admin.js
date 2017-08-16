jQuery(document).ready(function ($) {

	"use strict"

	$('#bruteguard-page')
		.on('focus', 'input', function () {
			$(this).attr('data-placeholder', $(this).attr('placeholder'));
			$(this).attr('placeholder', '');
		})
		.on('blur', 'input', function () {
			$(this).attr('placeholder', $(this).attr('data-placeholder'));
		});

	$('.bruteguard-stats')
		.find('.stats-label').each(function () {
			var $this = $(this),
				count = $this.data('count'),
				rate = $this.data('rate');
			$this.html(number_format(count, 0));
			if (rate) {
				setInterval(function () {
					$this.html(number_format(++count, 0));
				}, 600000 / rate);
			}


		});

	function number_format(number, decimals, decPoint, thousandsSep) {

		number = (number + '').replace(/[^0-9+\-Ee.]/g, '')
		var n = !isFinite(+number) ? 0 : +number
		var prec = !isFinite(+decimals) ? 0 : Math.abs(decimals)
		var sep = (typeof thousandsSep === 'undefined') ? ',' : thousandsSep
		var dec = (typeof decPoint === 'undefined') ? '.' : decPoint
		var s = ''
		var toFixedFix = function (n, prec) {
			var k = Math.pow(10, prec)
			return '' + (Math.round(n * k) / k)
				.toFixed(prec)
		}
		s = (prec ? toFixedFix(n, prec) : '' + Math.round(n)).split('.')
		if (s[0].length > 3) {
			s[0] = s[0].replace(/\B(?=(?:\d{3})+(?!\d))/g, sep)
		}
		if ((s[1] || '').length < prec) {
			s[1] = s[1] || ''
			s[1] += new Array(prec - s[1].length + 1).join('0')
		}
		return s.join(dec)
	}

});