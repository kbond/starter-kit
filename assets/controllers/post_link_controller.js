import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        url: String,
        method: {
            type: String,
            default: 'POST'
        },
        csrfToken: String,
        confirm: String,
    };

    click(event) {
        event.preventDefault();

        const href = this.urlValue || this.element.href;
        const form = document.createElement('form');
        const method = this.methodValue.toUpperCase();

        if (this.confirmValue && !confirm(this.confirmValue)) {
            return;
        }

        form.method = 'POST';

        if (href) {
            form.action = href;
        }

        if (method !== 'POST') {
            const input = document.createElement('input');

            input.type = 'hidden';
            input.name = '_method';
            input.value = method;
            form.appendChild(input);
        }

        if (this.csrfTokenValue) {
            const input = document.createElement('input');

            input.type = 'hidden';
            input.name = '_csrf_token';
            input.value = this.csrfTokenValue;
            form.appendChild(input);
        }

        document.body.appendChild(form);
        form.submit();
    }
}
