(function () {
    const ENDPOINT = '/frais_deplacementv2/api/convert_currency.php';

    function getElements(index) {
        const amountInput = document.querySelector(`input.montant-input[data-index="${index}"]`);
        const currencySelect = document.querySelector(`select.currency-select[data-index="${index}"]`);
        const resultInput = document.querySelector(`input.montant-mad-input[data-index="${index}"]`);
        const hiddenInput = document.querySelector(`input.montant-mad-hidden[data-index="${index}"]`);

        if (!amountInput || !currencySelect || !resultInput || !hiddenInput) {
            return null;
        }

        return { amountInput, currencySelect, resultInput, hiddenInput };
    }

    function formatMadValue(value) {
        const parsed = Number(value);
        if (Number.isNaN(parsed)) {
            return '';
        }

        return `${parsed.toFixed(2)} MAD`;
    }

    function showError(elements, message) {
        elements.resultInput.value = message;
        elements.hiddenInput.value = '';
    }

    function clearResult(elements) {
        elements.resultInput.value = '';
        elements.hiddenInput.value = '';
    }

    function convert(index) {
        const elements = getElements(index);
        if (!elements) {
            return;
        }

        const amountValue = elements.amountInput.value.trim();

        if (amountValue === '') {
            clearResult(elements);
            return;
        }

        elements.resultInput.value = 'Conversion...';

        const formData = new FormData();
        formData.append('amount', amountValue);
        formData.append('currency', elements.currencySelect.value);

        fetch(ENDPOINT, {
            method: 'POST',
            body: formData,
        })
            .then(async (response) => {
                const payload = await response.json();
                return { ok: response.ok, data: payload };
            })
            .then(({ ok, data }) => {
                if (!ok || !data.success) {
                    showError(elements, 'Erreur de conversion');
                    return;
                }

                elements.resultInput.value = formatMadValue(data.converted);
                elements.hiddenInput.value = data.converted;
                elements.resultInput.dataset.rate = data.rate;
            })
            .catch(() => {
                showError(elements, 'Erreur réseau');
            });
    }

    function initForDetail(index) {
        const elements = getElements(index);
        if (!elements) {
            return;
        }

        if (!elements.amountInput.dataset.ccBound) {
            elements.amountInput.addEventListener('input', () => convert(index));
            elements.amountInput.addEventListener('change', () => convert(index));
            elements.amountInput.dataset.ccBound = 'true';
        }

        if (!elements.currencySelect.dataset.ccBound) {
            elements.currencySelect.addEventListener('change', () => convert(index));
            elements.currencySelect.dataset.ccBound = 'true';
        }
    }

    function initExisting() {
        document.querySelectorAll('.montant-input').forEach((input) => {
            const index = input.dataset.index;
            if (typeof index !== 'undefined') {
                initForDetail(index);
            }
        });
    }

    window.CurrencyConverterUI = {
        initForDetail,
        triggerConversion: convert,
        initExisting,
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initExisting);
    } else {
        initExisting();
    }
})();

