class FastPaginate {
    constructor(config) {
        console.log('config', config);
        this.url = config.actionUrl;
        this.key = config.key;
        this.wrapper = document.querySelector(config.wrapper);
        if (!this.wrapper) {
            return;
        }

        this.el = {
            items: this.wrapper.querySelector('.fp-items'),
            sort: this.wrapper.querySelector('.fp-sort'),
            formFilters: this.wrapper.querySelector('.fp-filters'),
            filterChange: this.wrapper.querySelector('.fp-filters.fp-filters-change'),
            loadMore: this.wrapper.querySelector('.fp-loadmore'),
            pagination: this.wrapper.querySelector('.fp-pagination'),
        };

        this.path = {
            urlMode: config.url_mode,
            separator: config.path_separator,
            page: config.page_name,
            sort: config.sort_name,
        };

        this.state = {
            current_page: config.page || 1,
            load_page: config.page || 1,
            total: config.total || 0,
            last_key: config.last_key || 0,
            show: config.show || 0,
            sort: {
                sortby: config.sortby || 'id',
                sortdir: config.sortdir || 'asc',
            },
            filters: config.filters || {},
        };

        this.addEventListeners();
        this.updateLayout();
        this.URLManager = new URLManager(this.path.separator);
    }

    addEventListeners() {
        this.el.loadMore?.addEventListener('click', async event => {
            const target = event.target.closest('.loadmore-link');
            if (target) {
                event.preventDefault();
                this.state.load_page = this.state.current_page + 1;

                const response = await this.fetch('loadmore');
                await this.response(response);
            }
        });

        this.el.pagination?.addEventListener('click', async event => {
            const target = event.target.closest('.pagination-link');
            if (target) {
                event.preventDefault();
                event.stopPropagation();

                const page = target.getAttribute('data-page') || target.textContent;
                this.state.load_page = parseInt(page, 10);

                const response = await this.fetch('paginate');
                await this.response(response);
                return false;
            }
        });

        this.el.sort?.addEventListener('change', async ({target}) => {
            let [sortby, sortdir] = target.value.split('-');
            this.state.sort.sortby = sortby;
            this.state.sort.sortdir = sortdir;
            const response = await this.fetch('sort');
            await this.response(response);
        });

        if (this.el.formFilters) {
            if (this.el.filterChange) {
                this.el.formFilters.addEventListener('change', async event => {
                    event.preventDefault();
                    let target = event.target;
                    if (target.name.includes('[min]') || target.name.includes('[max]')) {
                        let name = target.name.replace('[min]', '').replace('[max]', '');
                        let inputMin = this.el.formFilters.querySelector(`[name="${name}[min]"]`);
                        let inputMax = this.el.formFilters.querySelector(`[name="${name}[max]"]`);
                        if (inputMin && inputMax) {
                            this.state.filters[name] = {
                                min: inputMin.value,
                                max: inputMax.value,
                            }
                        }
                    } else if (target.name.includes('[]')) {
                        let name = target.name.replace('[]', '');
                        if (!(name in this.state.filters)) {
                            this.state.filters[name] = [];
                        }

                        this.state.filters[name] = target.checked
                            ? [...new Set([...this.state.filters[name], target.value])]
                            : this.state.filters[name].filter(value => value !== target.value);

                    } else {
                        if (target.value === '' && target.name in this.state.filters) {
                            delete this.state.filters[target.name];
                        } else {
                            this.state.filters[target.name] = target.value;
                        }
                    }
                    this.state.last_key = 0;
                    this.state.current_page = 0;
                    this.state.load_page = 1;
                    const response = await this.fetch('filters');
                    await this.response(response);
                });
            }

            this.el.formFilters.addEventListener('submit', event => {
                event.preventDefault();
                this.submitForm();
            });

            this.el.formFilters.addEventListener('reset', event => {
                this.resetForm();
            });
        }
    }

    updateLayout() {
        if (this.el.sort) {
            let sort = this.state.sort.sortby + '-' + this.state.sort.sortdir;
            let value = this.el.sort.querySelector(`[value="${sort}"]`);
            if (value) {
                value.selected = true;
            }
        }

        if (this.el.formFilters) {
            Object.entries(this.state.filters).forEach(([name, value]) => {
                if (typeof value === 'object' && ('min' in value) && ('max' in value)) {
                    Object.entries(value).forEach(([key, val]) => {
                        let input = this.el.formFilters.querySelector(`[name="${name}[${key}]"]`);
                        if (input) {
                            input.value = val;
                        }
                    });
                } else {
                    let input = this.el.formFilters.querySelector(`[name="${name}"]`) || this.el.formFilters.querySelector(`[name="${name}[]"]`);
                    if (input) {
                        switch (input.type) {
                            case 'radio':
                                input = this.el.formFilters.querySelector(`[name="${name}"][value="${value}"]`);
                                if (input) {
                                    input.checked = true;
                                }
                                break;
                            case 'checkbox':
                                value.forEach(val => {
                                    let item = this.el.formFilters.querySelector(`[name="${name}[]"][value="${val}"]`);
                                    if (item) {
                                        item.checked = true;
                                    }
                                })
                            default:
                                input.value = value;
                        }
                    }
                }
            });
        }
    }

    async submitForm() {
        this.state.last_key = 0;
        this.state.current_page = 0;
        this.state.load_page = 1;
        this.URLManager.delete('page');
        const data = new FormData(this.el.formFilters);
        const jsonData = this.formDataToObject(data);
        this.state.filters = this.formDataToObject(new FormData(this.el.formFilters));
        const response = await this.fetch('filters');
        await this.response(response);
    }

    async resetForm() {
        this.state.last_key = 0;
        this.state.current_page = 0;
        this.state.load_page = 1;
        let items = this.el.formFilters.querySelectorAll("[name]");
        items.forEach(item => {
            this.URLManager.delete(item.name.replace(/\[\]|\[min\]|\[max\]/g, ''));
        });
        this.URLManager.delete('page');
        this.state.filters = {};
        const response = await this.fetch('filters');
        await this.response(response);
    }

    formDataToObject(formData) {
        const object = {};
        formData.forEach((value, key) => {
            const keys = key.match(/[^\[\]]+/g);
            keys.reduce((acc, cur, i) => {
                if (i === keys.length - 1) {
                    if (key.endsWith('[]')) {
                        acc[cur] = acc[cur] || [];
                        acc[cur].push(value);
                    } else if (Array.isArray(acc[cur])) {
                        acc[cur].push(value);
                    } else {
                        acc[cur] = value;
                    }
                } else {
                    acc[cur] = acc[cur] || {};
                }
                return acc[cur];
            }, object);
        });
        return object;
    }

    async fetch(action, fields = {}) {
        const data = {
            action: action,
            key: this.key,
            total: this.state.total,
            current_page: this.state.current_page,
            load_page: this.state.load_page,
            last_key: this.state.last_key,
            sortby: this.state.sort.sortby,
            sortdir: this.state.sort.sortdir,
            filters: this.state.filters,
            ...fields
        };

        const response = await fetch(this.url, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });
        if (!response.ok) {
            throw new Error('Failed to fetch data from server');
        }
        return response.json();
    }

    async response(response) {
        console.log('response', response);
        if (response?.action === 'loadmore') {
            this.el.items.insertAdjacentHTML('beforeend', response.output);
        } else {
            this.el.items.innerHTML = response.output;
        }

        this.state.current_page = response.page || 1;
        this.state.last_key = response?.last_key || '';
        this.state.total = response.total || 0;
        this.state.show = response.show || 0;

        this.updatePagination(response);
        this.updateVariables();
        this.updateURL();
    }


    updatePagination(response) {
        if (this.el.loadMore) {
            this.el.loadMore.innerHTML = response?.templates.loadmore || '';
        }

        if (this.el.pagination) {
            this.el.pagination.innerHTML = response?.templates.pagination || '';
        }
    }

    updateVariables() {
        let totals = this.wrapper.querySelectorAll('.fp-total');
        totals.forEach(total => {
            total.innerText = this.state.total.toLocaleString('en-US', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 0,
                useGrouping: true
            }).replace(/,/g, ' ');
        });
    }

    updateURL() {
        if (this.path.urlMode === 'get') {
            Object.entries(this.state).forEach(([name, value]) => {
                switch (name) {
                    case 'load_page':
                        if (this.state.load_page === 1) {
                            this.URLManager.delete(this.path.page);
                        } else {
                            this.URLManager.set(this.path.page, this.state.load_page);
                        }
                        break;
                    case 'sort':
                        this.URLManager.set(this.path.sort, `${this.state.sort.sortby}-${this.state.sort.sortdir}`);
                        break;
                    case 'filters':
                        Object.entries(this.state.filters).forEach(([name, value]) => {
                            if (value === '') {
                                this.URLManager.delete(name);
                            } else if (typeof value === 'string') {
                                this.URLManager.set(name, value);
                            } else if (typeof value === 'object') {
                                let prepareValue = [];
                                let separator = ',';
                                Object.entries(value).forEach(([key, val]) => {
                                    if (key === 'min' || key === 'max') {
                                        separator = '-';
                                    }
                                    if (val !== '') {
                                        prepareValue.push(val);
                                    }
                                });
                                value = prepareValue.join(separator);
                                if (value === '') {
                                    this.URLManager.delete(name);
                                } else {
                                    this.URLManager.set(name, value);
                                }
                            }
                        });
                        break;
                }
            });
        } else {
            this.URLManager = new URLManager(this.path.separator);
            Object.entries(this.state).forEach(([name, value]) => {
                switch (name) {
                    case 'load_page':
                        let pathPage = `${this.path.page}=${this.state.load_page}`;
                        if (this.state.load_page === 1) {
                            this.URLManager.removePath(pathPage);
                        } else {
                            this.URLManager.addPath(pathPage);
                        }
                        break;
                    case 'sort':
                        this.URLManager.addPath(`${this.path.sort}=${this.state.sort.sortby}-${this.state.sort.sortdir}`);
                        break;
                    case 'filters':
                        Object.entries(this.state.filters).forEach(([name, value]) => {
                            if (typeof value === 'array') {
                                value = value.join(',');
                            } else if (typeof value === 'object') {
                                let prepareValue = [];
                                let separator = ',';
                                Object.entries(value).forEach(([key, val]) => {
                                    if (key === 'min' || key === 'max') {
                                        separator = '-';
                                    }
                                    if (val !== '') {
                                        prepareValue.push(val);
                                    }
                                });
                                value = prepareValue.join(separator);
                            }

                            if (value !== '') {
                                this.URLManager.addPath(`${name}=${value}`);
                            }
                        });
                        break;
                }
            });
        }
        this.URLManager.updateURL();
    }

}

class URLManager {
    constructor(separator = ';') {
        this.url = new URL(window.location.href);
        this.pathSegments = [];
        this.pathSeparator = separator;
    }

    has(param) {
        return this.url.searchParams.has(param);
    }

    get(param) {
        return this.url.searchParams.get(param);
    }

    set(param, value) {
        this.url.searchParams.set(param, value);
        return this;
    }

    append(param, value) {
        this.url.searchParams.append(param, value);
        return this;
    }

    delete(param) {
        this.url.searchParams.delete(param);
        return this;
    }

    addPath(path) {
        if (!this.pathSegments.includes(path)) {
            this.pathSegments.push(path);
            this.updatePath();
        }
        return this;
    }

    removePath(path) {
        this.pathSegments = this.pathSegments.filter(p => p !== path);
        this.updatePath();
        return this;
    }

    updatePath() {
        this.url.pathname = `/${this.pathSegments.join(this.pathSeparator)}/`;
    }

    getURL() {
        return this.url.toString();
    }

    updateURL() {
        window.history.pushState({}, '', this.getURL());
    }
}
