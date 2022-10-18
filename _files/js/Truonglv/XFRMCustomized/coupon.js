!(function ($, window, document, _undefined) {
    XF.XFRMCustomized_CouponCheck = XF.Element.newHandler({
        options: {
            couponInput: null,
            href: null,
            price: null,
            total: null,
            licenseInput: null,
        },

        $input: null,
        $price0: null,
        $price1: null,
        $total0: null,
        $total1: null,

        loading: false,
        orgData: null,
        cacheData: {},

        $licenseInput: null,

        init: function () {
            if (!this.options.href) {
                throw new Error('Must have data-href attribute.');
            }

            this.$target.prop('disabled', true).addClass('is-disabled');
            this.$licenseInput = XF.findRelativeIf(this.options.licenseInput, this.$target);

            this.$input = XF.findRelativeIf(this.options.couponInput, this.$target);
            if (!this.$input.length) {
                throw new Error('Not found any coupon inputs');
            }
            this.$input.on('keyup', $.proxy(this, 'onInputKeyup'));

            this.setup();

            this.$target.bind('click', XF.proxy(this, 'click'));
        },

        setup: function () {
            this.$price0 = $(this.options.price);
            this.$price1 = this.$price0
                .clone()
                .attr('id', '')
                .css({ fontSize: 15, marginLeft: 10, textDecoration: 'line-through' })
                .hide();
            this.$price1.insertAfter(this.$price0);

            this.$total0 = $(this.options.total);
            this.$total1 = this.$total0
                .clone()
                .attr('id', '')
                .css({ fontSize: 15, marginLeft: 10, textDecoration: 'line-through' })
                .hide();
            this.$total1.insertAfter(this.$total0);

            this.$total0.css({ color: 'green' });
        },

        onInputKeyup: function () {
            var value = this.$input.val().trim();

            if (value.length) {
                this.$target.prop('disabled', false).removeClass('is-disabled');
            } else {
                this.$target.prop('disabled', true).addClass('is-disabled');
            }

            // reset state.
            this.$price1.hide();
            this.$total1.hide();

            if (this.orgData) {
                this.$price0.text(this.orgData.price);
                this.$total0.text(this.orgData.total);
            }
        },

        click: function (e) {
            e.preventDefault();

            if (this.loading) {
                return;
            }

            var _this = this,
                data = {
                    coupon_code: this.$input.val(),
                    [this.$licenseInput.attr('name')]: this.$licenseInput.val(),
                };

            if (this.cacheData[data.coupon_code]) {
                this.onResponse(this.cacheData[data.coupon_code]);

                return;
            }

            this.loading = true;
            _this.$input.prop('disabled', true);

            XF.ajax('POST', this.options.href, data, XF.proxy(this, 'onResponse')).always(function () {
                _this.loading = false;
                _this.$input.prop('disabled', false);
            });
        },

        onResponse: function (data) {
            this.cacheData[this.$input.val()] = data;

            if (this.orgData === null) {
                this.orgData = {
                    price: this.$price0.text(),
                    total: this.$total0.text(),
                };
            }

            if (data.hasOwnProperty('newTotal')) {
                this.$total0.text(data.newTotal);
                this.$total1.text(this.orgData.total).show();
            }

            if (data.hasOwnProperty('newPrice')) {
                this.$price0.text(data.newPrice);
                this.$price1.text(this.orgData.price).show();
            }
        },
    });

    XF.XFRMCustomized_PriceCalc = XF.Element.newHandler({
        options: {
            paymentProfiles: null,
            inputSelector: null,
            estimateUrl: null,
        },

        $paymentProfiles: null,
        $priceInput: null,
        $results: null,

        xhr: null,

        init: function () {
            this.$paymentProfiles = XF.findRelativeIf(this.options.paymentProfiles, this.$target);
            this.$paymentProfiles.bind('change', XF.proxy(this, 'showPurchasePrices'));

            if (this.$target[0].nodeName === 'INPUT') {
                this.$target.bind('change', XF.proxy(this, 'onChangePrice'));
                this.$priceInput = this.$target;
            } else {
                var $input = this.$target.find(this.options.inputSelector);
                $input.bind('change', XF.proxy(this, 'onChangePrice'));

                this.$priceInput = $input;
            }

            var $results = $('<ul />').addClass('listPlain listInline--bullet');
            this.$results = $results;

            $results.insertAfter(this.$target);

            this.showPurchasePrices();
        },

        onChangePrice: function () {
            this.showPurchasePrices();
        },

        showPurchasePrices: function () {
            var selectedPaymentProfiles = [],
                $paymentProfileInputs = this.$paymentProfiles.find('input'),
                _this = this;
            for (var i = 0; i < $paymentProfileInputs.length; i++) {
                var $paymentProfile = $($paymentProfileInputs[i]);

                if ($paymentProfile.is(':checked')) {
                    selectedPaymentProfiles.push($paymentProfile.val());
                }
            }

            if (this.xhr) {
                this.xhr.abort();
            }

            this.xhr = XF.ajax(
                'POST',
                this.options.estimateUrl,
                {
                    price: this.$priceInput.val(),
                    payment_profile_ids: selectedPaymentProfiles.join(','),
                },
                function (data) {
                    _this.$results.empty();
                    for (var j = 0; j < data.prices.length; j++) {
                        var price = data.prices[j];
                        var $li = $('<li />').text(price.label + ' (' + price.amount + ')');
                        $li.appendTo(_this.$results);
                    }
                }
            ).always(function () {
                _this.xhr = null;
            });
        },
    });

    XF.XFRMCustomized_TotalLicenses = XF.Element.newHandler({
        options: {
            basePrice: null,
            total: null,
        },

        $total: null,

        init: function () {
            this.$target.on('change', XF.proxy(this, 'onChange'));

            this.$total = XF.findRelativeIf(this.options.total, this.$target);
        },

        onChange: function () {
            var value = this.$target.val(),
                price = this.options.basePrice * parseFloat(value),
                priceText = this.$total.text();

            this.$total.text(priceText.substring(0, 1) + price);
        },
    });

    XF.Click.register('xfrmc-check-coupon-code', 'XF.XFRMCustomized_CouponCheck');
    XF.Element.register('xfrmc-price-calc', 'XF.XFRMCustomized_PriceCalc');
    XF.Element.register('xfrmc-total-licenses', 'XF.XFRMCustomized_TotalLicenses');
})(jQuery, this, document);
