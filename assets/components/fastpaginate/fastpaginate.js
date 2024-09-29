class FastPaginate {
    constructor(config) {
        console.log(config);
        this.url = config.url;
        this.key = config.key;
        this.wrapper = document.querySelector(config.wrapper);
        this.path = config.path || '?page={page}';
        if (!this.wrapper) {
            return;
        }
        this.load_page = this.current_page = config.page;
        this.show = 0;
        this.total = config.total;
        this.last_key = config.last_key;
        this.items = this.wrapper.querySelector('.fp-items');
        this.loadMore = this.wrapper.querySelector('.fp-loadmore');
        this.pagination = this.wrapper.querySelector('.fp-pagination');

        this.addEventListeners();
    }

    updateURL() {
        let newPath = this.path.replace('{page}', this.load_page);
        if (this.load_page === 1) {
            newPath = this.path.includes('?') ? window.location.pathname : '/';
        }
        window.history.pushState({}, '', newPath);
    }

    addEventListeners() {
        this.loadMore?.addEventListener('click', async (event) => {
            event.preventDefault();
            this.load_page = this.current_page + 1;

            const response = await this.fetch('loadmore');
            await this.response(response);
        });

        this.pagination?.addEventListener('click', async (event) => {
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
    }

    async fetch(action, fields) {
        const data= new FormData();
        data.append('action', action);
        data.append('key', this.key);
        data.append('total', this.total);
        data.append('current_page', this.current_page);
        data.append('load_page', this.load_page);
        data.append('last_key', this.last_key);
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
}