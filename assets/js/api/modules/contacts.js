import axios from 'axios';

const endpoint = process.env.HOST+'/api/v1/contacts';

export default {

    getAll() {
        return axios.get(endpoint);
    },

    getFiltered(params) {
        return axios.get(endpoint, {
            params,
        });
    },

    get(id) {
        return axios.get(endpoint+'/'+id);
    },

    create(payload) {
        return axios.post(endpoint, payload);
    },

    update(id, payload) {
        return axios.put(endpoint+'/'+id, payload);
    },

    delete(id) {
        return axios.delete(endpoint+'/'+id);
    },

    getRegionByContact(contactId) {
        return axios.get(endpoint + `/${contactId}/regions`);
    },

    removeRegionConnections(contactId) {
        return axios.delete(endpoint + `/${contactId}/regions`);
    },

};