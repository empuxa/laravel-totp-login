import Alpine from 'alpinejs';

Alpine.data('code', (length) => ({
    length,
    field(i) { return this.$root.querySelector(`#code_field_${i}`); },
    resetValue(i) {
        for (let x = i; x < this.length; x++) this.field(x).value = '';
    },
    stepForward(i) {
        const field = this.field(i);
        field.value = field.value.replace(/[^0-9]/g, '').slice(-1);
        if (!field.value) return;
        if (i === this.length - 1) {
            this.$root.querySelector('button[type="submit"]').focus();
        } else {
            this.field(i + 1).focus();
        }
    },
    stepBack(i) {
        this.field(i).value = '';
        if (i > 0) this.field(i - 1).focus();
    },
    paste(event) {
        if (!event.target.matches('input[name="code[]"]')) return;
        const text = event.clipboardData?.getData('text').replace(/\s+/g, '') ?? '';
        event.preventDefault();
        if (!/^[0-9]+$/.test(text) || text.length !== this.length) return;
        for (let i = 0; i < this.length; i++) this.field(i).value = text[i];
        const submit = this.$root.querySelector('button[type="submit"]');
        submit.focus();
        submit.click();
    },
}));

Alpine.start();
