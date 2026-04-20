const wpPot = require('wp-pot');

wpPot({
	destFile: './languages/cardlink-payment-gateway.pot',
	domain: 'cardlink-payment-gateway',
	package: 'Cardlink Payment Gateway',
	src: 'src/*.php'
});