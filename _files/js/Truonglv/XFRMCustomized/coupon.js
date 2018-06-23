!function ($, window, document, _undefined) {
    XF.XFRMCustomized_CouponCheck = XF.Element.newHandler({
        options: {
            resourceId: null,
            couponInput: null,
            href: null,
            price: null,
            fee: null,
            total: null
        },

        $input: null,
        $price0: null,
        $price1: null,
        $fee0: null,
        $fee1: null,
        $total0: null,
        $total1: null,

        loading: false,
        orgData: null,
        cacheData: {},

        init: function () {
            if (!this.options.href) {
                throw new Error('Must have data-href attribute.');
            }

            this.$target.prop('disabled', true).addClass('is-disabled');

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
            this.$price1 = this.$price0.clone().attr('id', '')
                .css({ fontSize: 15, marginLeft: 10, textDecoration: 'line-through' })
                .hide();
            this.$price1.insertAfter(this.$price0);

            this.$fee0 = $(this.options.fee);
            this.$fee1 = this.$fee0.clone().attr('id', '')
                .css({ marginLeft: 10, textDecoration: 'line-through' })
                .hide();
            this.$fee1.insertAfter(this.$fee0);

            this.$total0 = $(this.options.total);
            this.$total1 = this.$total0.clone().attr('id', '')
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
            this.$fee1.hide();
            this.$total1.hide();

            if (this.orgData) {
                this.$price0.text(this.orgData.price);
                this.$fee0.text(this.orgData.fee);
                this.$total0.text(this.orgData.total);
            }
        },

        click: function (e) {
            e.preventDefault();

            if (this.loading) {
                return;
            }

            var _this = this,
                data = { resource_id: this.options.resourceId, coupon_code: this.$input.val() };

            if (this.cacheData[data.coupon_code]) {
                this.onResponse(this.cacheData[data.coupon_code]);

                return;
            }

            this.loading = true;
            _this.$input.prop('disabled', true);

            XF.ajax('POST', this.options.href, data, XF.proxy(this, 'onResponse'))
                .always(function () {
                    _this.loading = false;
                    _this.$input.prop('disabled', false);
                });
        },

        onResponse: function (data) {
            this.cacheData[this.$input.val()] = data;

            if (this.orgData === null) {
                this.orgData = {
                    price: this.$price0.text(),
                    fee: this.$fee0.text(),
                    total: this.$total0.text()
                };
            }

            this.$total0.text(data.newTotal);
            this.$total1.text(this.orgData.total).show();

            this.$price0.text(data.newPrice);
            this.$price1.text(this.orgData.price).show();

            this.$fee0.text(data.newFee);
            this.$fee1.text(this.orgData.fee).show();
        }
    });

    XF.Click.register('xfrmc-check-coupon-code', 'XF.XFRMCustomized_CouponCheck');
}
(jQuery, this, document);