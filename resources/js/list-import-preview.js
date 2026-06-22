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

            const parsed = parseDelimitedText(await file.text(), this.separator || ';', this.delimiter || '"');
            this.columns = buildColumnConfig(parsed.headers);
            this.previewRows = parsed.previewRows;

            if (this.columns.length === 0) {
                this.error = 'O arquivo precisa ter cabecalho.';
            }
        },
        refreshPreview() {
            if (this.currentFile) {
                this.loadFile(this.currentFile);
            }
        },
        choose(event) {
            this.loadFile(event.target.files?.[0]);
        },
        drop(event) {
            if (this.$refs?.file && event.dataTransfer?.files) {
                this.$refs.file.files = event.dataTransfer.files;
            }

            this.loadFile(event.dataTransfer.files?.[0]);
        },
        canSubmit() {
            return hasPhoneColumn(this.columns);
        },
    };
}
