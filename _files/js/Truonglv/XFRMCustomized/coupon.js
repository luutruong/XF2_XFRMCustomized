!function ($, window, document, _undefined) {
    XF.XFRMCustomized_CouponCheck = XF.Click.newHandler({
        eventNameSpace: 'XFRMCustomized_CouponCheck',

        options: {
            resourceId: null,
            couponInput: null,
            href: null
        },

        $input: null,
        loading: false,

        init: function () {
            if (!this.options.href) {
                throw new Error('Must have data-href attribute.');
            }

            this.$input = XF.findRelativeIf(this.options.couponInput, this.$target);
            if (!this.$input.length) {
                throw new Error('Not found any coupon inputs');
            }
        },

        click: function (e) {
            e.preventDefault();

            if (this.loading) {
                return;
            }

            var _this = this,
                data = { resource_id: this.options.resourceId, coupon_code: this.$input.val() };

            _this.$input.prop('disabled', true);

            XF.ajax('POST', this.options.href, data, XF.proxy(this, 'onResponse'))
                .always(function () {
                    _this.loading = false;
                    _this.$input.prop('disabled', false);
                });
        },

        onResponse: function (data) {
            console.log(data);
        }
    });

    XF.Click.register('xfrmc-check-coupon-code', 'XF.XFRMCustomized_CouponCheck');
}
(jQuery, this, document);