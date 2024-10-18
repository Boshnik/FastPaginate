class FastPaginate {
    constructor(config) {
        console.log(config);
        this.url = config.actionUrl;
        this.key = config.key;
        this.wrapper = document.querySelector(config.wrapper);
        if (!this.wrapper) {
            return;
        }

        this.el = {
            items: this.wrapper.querySelector('.fp-items'),
            loadMore: this.wrapper.querySelector('.fp-loadmore'),
            pagination: this.wrapper.querySelector('.fp-pagination'),
            sort: this.wrapper.querySelector('.fp-sort'),
            formFilters: this.wrapper.querySelector('.fp-filters'),
            filterChange: this.wrapper.querySelector('.fp-filters.fp-filters-change'),
        };

        this.path = {
            urlMode: config.url_mode,
            separator: config.path_separator,
            page: {
                name: config.path_page_name,
                tpl: config.path_page,
            },
            sort: {
                name: config.path_sort_name,
                tpl: config.path_sort,
            },
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
        this.URLManager = new URLManager();
    }

    addEventListeners() {
        this.el.loadMore?.addEventListener('click', async event => {
            event.preventDefault();
            this.state.load_page = this.state.current_page + 1;

            const response = await this.fetch('loadmore');
            await this.response(response);
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
                this.el.formFilters.addEventListener('change', event => {
                    event.preventDefault();
                    this.filtersForm();
                });
            }

            this.el.formFilters.addEventListener('submit', event => {
                event.preventDefault();
                this.filtersForm();
            });
        }
    }

    updateLayout() {
        if (this.el.sort) {
            let sort = this.state.sortby + '-' + this.state.sortdir;
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

    async filtersForm() {
        this.state.last_key = 0;
        this.state.current_page = 0;
        this.state.load_page = 1;
        this.URLManager.delete('page');
        const data = new FormData(this.el.formFilters);
        const jsonData = this.formDataToObject(data);
        const response = await this.fetch('filters', {filters: jsonData});
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

    async fetch(action, fields) {
        const data = {
            action: action,
            key: this.key,
            total: this.state.total,
            current_page: this.state.current_page,
            load_page: this.state.load_page,
            last_key: this.state.last_key,
            sortby: this.state.sort.sortby,
            sortdir: this.state.sort.sortdir,
            ...fields
        };
        if (action !== 'filters') {
            data.filters = this.formDataToObject(new FormData(this.el.formFilters));
        }
        this.state.filters = data.filters || {};

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

        this.state.current_page = response.page;
        this.state.last_key = response?.last_key || '';
        this.state.total = response.total;
        this.state.show = response.show;

        this.updatePagination(response);
        this.updateVariables();
        this.updateURL();
    }


    updatePagination(response) {
        if (this.el.loadMore) {
            this.el.loadMore.style.display = (response?.next_link === '#') ? 'none' : 'flex';
            this.el.loadMore.setAttribute('href', response?.next_link || '');
        }

        if (this.el.pagination) {
            if (response?.tpl_pagination === '') {
                this.el.pagination.innerHTML = '';
            } else {
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = response.tpl_pagination;
                const paginationContent = tempDiv.querySelector('.fp-pagination').innerHTML;
                this.el.pagination.innerHTML = paginationContent;
            }
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
                            this.URLManager.delete(this.path.page.name);
                        } else {
                            this.URLManager.set(this.path.page.name, this.state.load_page);
                        }
                        break;
                    case 'sort':
                        this.URLManager.set(this.path.sort.name, `${this.state.sort.sortby}-${this.state.sort.sortdir}`);
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
        }
        this.URLManager.updateURL();
    }

}

class URLManager {
    constructor(url = window.location.href) {
        this.url = new URL(url);
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

    getURL() {
        return this.url.toString();
    }

    updateURL() {
        window.history.pushState({}, '', this.getURL());
    }
}
