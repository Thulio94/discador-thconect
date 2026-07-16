export const PREVIEW_READ_BYTES = 512 * 1024;

export async function readPreviewText(file) {
    const previewSource = typeof file.slice === 'function'
        ? file.slice(0, PREVIEW_READ_BYTES)
        : file;

    return previewSource.text();
}

export function parseDelimitedText(text, separator = ';', delimiter = '"') {
    const rows = [];
    let cell = '';
    let row = [];
    let quoted = false;
    const separatorChar = separator?.[0] ?? ';';
    const delimiterChar = delimiter?.[0] ?? '"';

    for (let index = 0; index < text.length; index += 1) {
        const char = text[index];
        const next = text[index + 1];

        if (char === delimiterChar && quoted && next === delimiterChar) {
            cell += delimiterChar;
            index += 1;
            continue;
        }

        if (char === delimiterChar) {
            quoted = !quoted;
            continue;
        }

        if (char === separatorChar && !quoted) {
            row.push(cell.trim());
            cell = '';
            continue;
        }

        if ((char === '\n' || char === '\r') && !quoted) {
            if (char === '\r' && next === '\n') {
                index += 1;
            }

            row.push(cell.trim());
            rows.push(row);
            row = [];
            cell = '';
            continue;
        }

        cell += char;
    }

    row.push(cell.trim());
    rows.push(row);

    const headers = (rows.shift() ?? []).map((header) => header.trim());

    return {
        headers,
        previewRows: rows
            .filter((item) => item.some((cellValue) => cellValue !== ''))
            .slice(0, 2),
    };
}

export function buildColumnConfig(headers) {
    return headers
        .map((name, index) => ({
            index,
            name: name.trim(),
            enabled: name.trim() !== '',
            type: 'extra',
        }))
        .filter((column) => column.name !== '');
}

export function hasPhoneColumn(columns) {
    return columns.some((column) => column.enabled && column.type === 'phone');
}

export function listImportPreview() {
    return {
        fileName: '',
        separator: ';',
        delimiter: '"',
        currentFile: null,
        columns: [],
        previewRows: [],
        error: '',
        isReading: false,
        isSubmitting: false,
        async loadFile(file) {
            this.error = '';
            this.fileName = file?.name ?? '';
            this.currentFile = file ?? null;

            if (!file) {
                return;
            }

            if (!/\.(csv|txt)$/i.test(file.name)) {
                this.error = 'Envie um arquivo CSV ou TXT.';
                this.columns = [];
                this.previewRows = [];
                return;
            }

            this.isReading = true;

            try {
                const parsed = parseDelimitedText(await readPreviewText(file), this.separator || ';', this.delimiter || '"');
                this.columns = buildColumnConfig(parsed.headers);
                this.previewRows = parsed.previewRows;

                if (this.columns.length === 0) {
                    this.error = 'O arquivo precisa ter cabecalho.';
                }
            } catch {
                this.columns = [];
                this.previewRows = [];
                this.error = 'Nao foi possivel ler este arquivo. Verifique o formato CSV ou TXT.';
            } finally {
                this.isReading = false;
            }
        },
        refreshPreview() {
            if (this.currentFile) {
                this.loadFile(this.currentFile);
            }
        },
        choose(event) {
            if (this.isSubmitting) {
                return;
            }

            this.loadFile(event.target.files?.[0]);
        },
        drop(event) {
            if (this.isSubmitting) {
                return;
            }

            if (this.$refs?.file && event.dataTransfer?.files) {
                this.$refs.file.files = event.dataTransfer.files;
            }

            this.loadFile(event.dataTransfer.files?.[0]);
        },
        canSubmit() {
            return !this.isReading && !this.isSubmitting && hasPhoneColumn(this.columns);
        },
        submit(event) {
            const form = event.currentTarget;

            if (!this.canSubmit()) {
                return;
            }

            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            this.isSubmitting = true;
            this.error = '';

            window.requestAnimationFrame(() => form.submit());
        },
    };
}
