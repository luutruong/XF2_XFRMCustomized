(function () {
    XF.XFRMCustomized_CouponCheck = XF.Element.newHandler({
        options: {
            couponInput: null,
            href: null,
            price: null,
            total: null,
            licenseInput: null,
        },

        input: null,
        price0: null,
        price1: null,
        total0: null,
        total1: null,

        loading: false,
        orgData: null,
        cacheData: {},

        licenseInput: null,

        init() {
            if (!this.options.href) {
                throw new Error('Must have data-href attribute.');
            }

            this.target.disabled = true;
            this.target.classList.add('is-disabled');
            this.licenseInput = XF.findRelativeIf(this.options.licenseInput, this.target);

            this.input = XF.findRelativeIf(this.options.couponInput, this.target);
            if (!this.input) {
                throw new Error('Not found any coupon inputs');
            }
            XF.on(this.input, 'keyup', this.onInputKeyup.bind(this));

            this.setup();
            XF.on(this.target, 'click', this.click.bind(this));
        },

        setup() {
            this.price0 = document.getElementById(this.options.price);
            this.price1 = this.price0.cloneNode(true);
            this.setupCloneElement(this.price1);
            this.price1.after(this.price0);

            this.total0 = document.getElementById(this.options.total);
            this.total1 = this.total0.cloneNode(true);
            this.setupCloneElement(this.total1);
            this.total1.after(this.total0);

            this.total0.style.color = 'green';
        },

        setupCloneElement(element) {
            element.setAttribute('id', '');
            element.style.fontSize = 15;
            element.style.marginLeft = 10;
            element.style.textDecoration = 'line-through';
            element.style.display = 'none';
        },

        onInputKeyup() {
            const value = this.input.value;

            if (value.length) {
                this.target.disabled = false;
                this.target.classList.remove('is-disabled');
            } else {
                this.target.disabled = true;
                this.target.classList.add('is-disabled');
            }

            // reset state.
            this.price1.style.display = 'none';
            this.total1.style.display = 'none';

            if (this.orgData) {
                this.price0.innerText = this.orgData.price;
                this.total0.innerText = this.orgData.total;
            }
        },

        click: function (e) {
            e.preventDefault();

            if (this.loading) {
                return;
            }

            const data = {
                coupon_code: this.input.value,
                [this.licenseInput.getAttribute('name')]: this.licenseInput.value,
            };

            if (this.cacheData[data.coupon_code]) {
                this.onResponse(this.cacheData[data.coupon_code]);

                return;
            }

            this.loading = true;
            this.input.disabled = true;

            XF.ajax('POST', this.options.href, data, this.onResponse.bind(this)).finally(() => {
                this.loading = false;
                this.input.disabled = false;
            });
        },

        onResponse(data) {
            this.cacheData[this.input.value] = data;

            if (this.orgData === null) {
                this.orgData = {
                    price: this.price0.innerText,
                    total: this.total0.innerText,
                };
            }

            if (data.hasOwnProperty('newTotal')) {
                this.total0.innerText = data.newTotal;
                this.total1.innerText = this.orgData.total;
                this.total1.style.display = '';
            }

            if (data.hasOwnProperty('newPrice')) {
                this.price0.innerText = data.newPrice;
                this.price1.innerText = this.orgData.price;
                this.price1.style.display = '';
            }
        },
    });

    XF.XFRMCustomized_PriceCalc = XF.Element.newHandler({
        options: {
            paymentProfiles: null,
            inputSelector: null,
            estimateUrl: null,
        },

        paymentProfiles: null,
        priceInput: null,
        results: null,

        xhr: null,

        init() {
            this.paymentProfiles = XF.findRelativeIf(this.options.paymentProfiles, this.target);
            XF.on(this.paymentProfiles, 'change', this.showPurchasePrices.bind(this));

            if (this.target.nodeName === 'INPUT') {
                XF.on(this.target, 'change', this.onChangePrice.bind(this));
                this.priceInput = this.target;
            } else {
                const input = this.target.querySelector(this.options.inputSelector);
                XF.on(input, 'change', this.onChangePrice.bind(this));

                this.priceInput = input;
            }

            const results = document.createElement('ul');
            results.classList.add('listPlain');
            results.classList.add('listInline--bullet');

            this.results = results;
            results.after(this.target);

            this.showPurchasePrices();
        },

        onChangePrice() {
            this.showPurchasePrices();
        },

        showPurchasePrices() {
            var selectedPaymentProfiles = [],
                paymentProfileInputs = this.paymentProfiles.querySelectorAll('input');
            for (let i = 0; i < paymentProfileInputs.length; i++) {
                const paymentProfile = paymentProfileInputs[i];

                if (paymentProfile.checked) {
                    selectedPaymentProfiles.push(paymentProfile.value);
                }
            }

            if (this.xhr) {
                this.xhr.controller.abort();
            }

            this.xhr = XF.ajaxAbortable(
                'POST',
                this.options.estimateUrl,
                {
                    price: this.priceInput.value,
                    payment_profile_ids: selectedPaymentProfiles.join(','),
                },
                (data) => {
                    this.results.innerHTML = '';
                    for (let j = 0; j < data.prices.length; j++) {
                        const price = data.prices[j];

                        const liNode = document.createElement('li');
                        liNode.innerText = price.label + ' (' + price.amount + ')';
                        liNode.append(this.results);
                    }
                }
            );
            this.xhr.ajax.finally(() => {
                this.xhr = null;
            });
        },
    });

    XF.XFRMCustomized_TotalLicenses = XF.Element.newHandler({
        options: {
            basePrice: null,
            total: null,
        },

        total: null,

        init() {
            XF.on(this.target, 'change', this.onChange.bind(this));
            this.total = XF.findRelativeIf(this.options.total, this.target);
        },

        onChange() {
            var value = this.target.value,
                price = this.options.basePrice * parseFloat(value),
                priceText = this.total.innerText;

            this.total.innerText = priceText.substring(0, 1) + price;
        },
    });

    XF.Event.register('click', 'xfrmc-check-coupon-code', 'XF.XFRMCustomized_CouponCheck');
    XF.Element.register('xfrmc-price-calc', 'XF.XFRMCustomized_PriceCalc');
    XF.Element.register('xfrmc-total-licenses', 'XF.XFRMCustomized_TotalLicenses');
})();
