class FastPaginate {
    constructor(config) {
        console.log(config);
        this.url = config.actionUrl;
        this.key = config.key;
        this.wrapper = document.querySelector(config.wrapper);
        this.path = config.path || '?page={page}';
        if (!this.wrapper) {
            return;
        }
        this.load_page = this.current_page = config.page;
        this.show = 0;
        this.total = config.total;
        this.sortby = config.sortby;
        this.sortdir = config.sortdir;
        this.last_key = config.last_key;
        this.items = this.wrapper.querySelector('.fp-items');
        this.loadMore = this.wrapper.querySelector('.fp-loadmore');
        this.pagination = this.wrapper.querySelector('.fp-pagination');
        this.sort = this.wrapper.querySelector('.fp-sort');
        this.filters = this.wrapper.querySelector('.fp-filters');
        this.filterChange = this.wrapper.querySelector('.fp-filters-change') ? 1 : 0;
        if (this.sort) {
            let sort = this.sortby + '-' + this.sortdir;
            let value = this.sort.querySelector(`[value="${sort}"]`);
            if (value) {
                value.selected = true;
            }
        }

        this.urlMode = config.url_mode;
        this.pathSeparator = config.path_separator;
        this.pathPage = config.path_page;
        this.pathSort = config.path_sort;
        this.baseUrl = this.getBaseUrl();

        this.addEventListeners();
    }

    getBaseUrl() {
        let baseUrl = window.location.pathname;
        if (this.urlMode === 'url') {
            const pageString = this.pathPage.replace('{page}', this.current_page);
            const sortString = this.pathSort
                .replace('{sortby}', this.sortby)
                .replace('{sortdir}', this.sortdir);

            baseUrl = baseUrl.replace(pageString, '');
            baseUrl = baseUrl.replace(sortString, '');

            baseUrl = baseUrl.replace(new RegExp(`${this.pathSeparator}\/+$`), '');
            baseUrl = baseUrl.replace(/\/+/g, '/');
        }

        return baseUrl;
    }

    addEventListeners() {
        this.loadMore?.addEventListener('click', async event => {
            event.preventDefault();
            this.load_page = this.current_page + 1;

            const response = await this.fetch('loadmore');
            await this.response(response);
        });

        this.pagination?.addEventListener('click', async event => {
            const target = event.target.closest('.pagination-link');

            if (target) {
                event.preventDefault();
                event.stopPropagation();

                const page = target.getAttribute('data-page') || target.textContent;
                this.load_page = parseInt(page,10);

                const response = await this.fetch('paginate');
                await this.response(response);

                return false;
            }
        });

        this.sort?.addEventListener('change', async ({ target }) => {
            let [sortby, sortdir] = target.value.split('-');
            this.sortby = sortby;
            this.sortdir = sortdir;
            const response = await this.fetch('sort');
            await this.response(response);
        });

        if (this.filterChange) {
            this.filters?.addEventListener('change', event => {
                event.preventDefault();
                console.log(event.target.value);
                this.filtersForm();
            });
        }
        this.filters?.addEventListener('submit', event => {
            event.preventDefault();
            this.filtersForm();
        });
    }

    async filtersForm () {
        this.current_page = 0;
        this.load_page = 1;
        this.last_key = 0;
        const data = new FormData(this.filters);
        const jsonData = this.formDataToObject(data);
        const response = await this.fetch('filters', { filters: jsonData });
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
            total: this.total,
            current_page: this.current_page,
            load_page: this.load_page,
            last_key: this.last_key,
            sortby: this.sortby,
            sortdir: this.sortdir,
            ...fields
        };
        if (action !== 'filters') {
            data.filters = this.formDataToObject(new FormData(this.filters));
        }
        console.log('fetch data', data);

        const response= await fetch(this.url, {
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
        console.log(response?.action);
        if (response?.action === 'loadmore') {
            this.items.insertAdjacentHTML('beforeend', response.output);
        } else {
            this.items.innerHTML = response.output;
        }

        this.current_page = response.page;
        this.last_key = response?.last_key || '';
        this.total = response.total;
        this.show = response.show;

        this.updatePagination(response);
        this.updateVariables();
        this.updateURL();
    }


    updatePagination(response) {
        if (this.loadMore) {
            this.loadMore.style.display = (response?.next_link === '#') ? 'none' : 'flex';
            this.loadMore.setAttribute('href', response?.next_link || '');
        }

        if (this.pagination) {
            if (response?.tpl_pagination === '') {
                this.pagination.innerHTML = '';
            } else {
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = response.tpl_pagination;
                const paginationContent = tempDiv.querySelector('.fp-pagination').innerHTML;
                this.pagination.innerHTML = paginationContent;
            }
        }
    }

    updateVariables() {
        let totals = this.wrapper.querySelectorAll('.fp-total');
        totals.forEach(total => {
           total.innerText = this.total.toLocaleString('en-US', {
               minimumFractionDigits: 0,
               maximumFractionDigits: 0,
               useGrouping: true
           }).replace(/,/g, ' ');
        });
    }

    updateURL() {
        let newPath = this.baseUrl;

        const pagePath = this.pathPage.replace('{page}', this.load_page);
        const sortParam = this.pathSort
            .replace('{sortby}', this.sortby)
            .replace('{sortdir}', this.sortdir);


        if (this.load_page === 1) {
            if (this.urlMode === 'get') {
                newPath += sortParam ? `?${sortParam}` : '';
            } else if (this.urlMode === 'url') {
                newPath += sortParam ? `${sortParam}/` : '';
            }
        } else {
            if (this.urlMode === 'get') {
                newPath += `?${pagePath}` + (sortParam ? `&${sortParam}` : '').replace(/&$/, '');
            } else if (this.urlMode === 'url') {
                newPath += pagePath + (sortParam ? `${this.pathSeparator}${sortParam}/` : '/');
            }
        }

        window.history.pushState({}, '', newPath);
    }

}