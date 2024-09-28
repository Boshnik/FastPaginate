class FastPaginate {
    constructor(config) {
        this.url = config.url;
        this.key = config.key;
        this.wrapper = document.querySelector(config.wrapper);
        if (!this.wrapper) {
            return;
        }
        this.current_page = 1;
        this.show = 0;
        this.total = config.total;
        this.last_key = config.last_key;
        this.items = this.wrapper.querySelector('.fp-items');
        this.btnMore = this.wrapper.querySelector('.fp-more');

        this.addEventListeners();
    }

    addEventListeners() {
        this.btnMore?.addEventListener('click', async ({ target }) => {
            const response = await this.fetch('loadmore');
            await this.response(response);
        });
    }

    async fetch(action, fields) {
        const data= new FormData();
        data.append('action', action);
        data.append('key', this.key);
        data.append('total', this.total);
        data.append('current_page', this.current_page);
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
        switch (response?.action) {
            case 'loadmore':
                this.items.insertAdjacentHTML('beforeend', response.output);
                this.current_page = response.current_page;
                this.last_key = response.last_key;
                this.show = response.show;
                break;
        }
    }
}