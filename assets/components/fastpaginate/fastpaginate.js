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
            console.log(target.value);
            let [sortby, sortdir] = target.value.split('-');
            this.sortby = sortby;
            this.sortdir = sortdir;
            const response = await this.fetch('sort');
            await this.response(response);
        });
    }

    async fetch(action, fields) {
        const data= new FormData();
        data.append('action', action);
        data.append('key', this.key);
        data.append('total', this.total);
        data.append('current_page', this.current_page);
        data.append('load_page', this.load_page);
        data.append('last_key', this.last_key);
        data.append('sortby', this.sortby);
        data.append('sortdir', this.sortdir);
        for (let key in fields) {
            if (fields.hasOwnProperty(key)) {
                data.append(key, fields[key]);
            }
        }

        const response= await fetch(this.url, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
            },
            body: data
        });
        if (!response.ok) {
            throw new Error('Failed to fetch data from server');
        }
        return response.json();
    }

    async response(response) {
        console.log(response);
        switch (response?.action) {
            case 'loadmore':
                this.items.insertAdjacentHTML('beforeend', response.output);
                break;

            case 'paginate':
            case 'sort':
                this.items.innerHTML = response.output;
                break;
        }

        this.updatePagination(response);
        this.updateURL();
    }

    updatePagination(response) {
        this.current_page = response.current_page;
        this.last_key = response?.last_key || '';
        this.total = response.total;
        this.show = response.show;

        if (this.loadMore) {
            this.loadMore.style.display = (response?.next_link === '#') ? 'none' : 'flex';
            this.loadMore.setAttribute('href', response?.next_link || '');
        }

        if (this.pagination && response?.tpl_pagination !== '') {
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = response.tpl_pagination;
            const paginationContent = tempDiv.querySelector('.fp-pagination').innerHTML;
            this.pagination.innerHTML = paginationContent;
        }
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