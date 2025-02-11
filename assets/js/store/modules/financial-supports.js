import api from '../../api';

// initial state
const state = () => ({
    all: [],
    filtered: [],
    financialSupport: {},
});

// getters
const getters = {

    getById: (state) => (id) => {
        return state.all.find(item => item.id === id);
    },

};

// actions
const actions = {

    loadAll ({ commit }) {
        commit('loaders/showLoader', 'financialSupports', { root: true });
        return api.financialSupports.getAll().then((response) => {
            commit('loaders/hideLoader', 'financialSupports', { root: true });
            commit('setAll', response.data);
        });
    },

    loadFiltered ({ commit }, params) {
        commit('loaders/showLoader', 'financialSupports', { root: true });
        return api.financialSupports.getFiltered(params).then((response) => {
            commit('loaders/hideLoader', 'financialSupports', { root: true });
            commit('setFiltered', response.data);
            return response.data;
        });
    },

    load ({ commit }, id) {
        commit('loaders/showLoader', 'financialSupports/'+id, { root: true });
        return api.financialSupports.get(id).then((response) => {
            commit('loaders/hideLoader', 'financialSupports/'+id, { root: true });
            commit('set', response.data);
        });
    },

    create ({ commit }, payload) {
        commit('loaders/showLoader', 'financialSupports/create', { root: true });
        return api.financialSupports.create(payload).then((response) => {
            commit('loaders/hideLoader', 'financialSupports/create', { root: true });
            if(payload.addToInbox) {
                if(payload.inboxId) {
                    commit('inbox/update', response.data, { root: true });
                } else {
                    commit('inbox/insert', response.data, { root: true });
                }
            } else {
                commit('insert', response.data);
                commit('set', response.data);
            }
        });
    },

    update ({ commit }, payload) {
        commit('loaders/showLoader', 'financialSupports/'+payload.id, { root: true });
        return api.financialSupports.update(payload.id, payload).then((response) => {
            commit('loaders/hideLoader', 'financialSupports/'+payload.id, { root: true });
            if(payload.addToInbox) {
                if(payload.inboxId) {
                    commit('inbox/update', response.data, { root: true });
                } else {
                    commit('inbox/insert', response.data, { root: true });
                }
            } else {
                commit('update', response.data);
                commit('set', response.data);
            }
        });
    },

    delete ({ commit }, id) {
        commit('loaders/showLoader', 'financialSupports/'+id, { root: true });
        return api.financialSupports.delete(id).then((response) => {
            commit('loaders/hideLoader', 'financialSupports/'+id, { root: true });
            commit('remove', id);
        });
    },

};

// mutations
const mutations = {

    setAll (state, financialSupports) {
        state.all = financialSupports;
    },

    setFiltered (state, filtered) {
        state.filtered = filtered;
    },

    set (state, financialSupport) {
        if(financialSupport) {
            financialSupport = {
                ...financialSupport,
                translations: typeof financialSupport.translations === 'object' && financialSupport.translations !== null && !Array.isArray(financialSupport.translations) ? financialSupport.translations : {},
            };
        }
        state.financialSupport = financialSupport;
    },

    insert (state, financialSupport) {
        state.all = [...state.all, financialSupport];
    },

    update (state, financialSupport) {
        let existingFinancialSupport = state.all.find(p => p.id === financialSupport.id);
        if(existingFinancialSupport) {
            state.all[state.all.indexOf(existingFinancialSupport)] = financialSupport;
        }
    },

    remove (state, id) {
        state.all = state.all.filter((financialSupport) => {
            return parseInt(financialSupport.id) !== parseInt(id);
        });
    },

};

export default {
    namespaced: true,
    state,
    getters,
    actions,
    mutations
};