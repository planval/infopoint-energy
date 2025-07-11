<template>
    <div class="financial-support-contacts-modern">
        <div class="page-header">
            <div class="header-content">
                <h2>Förderhilfen - Kontakte</h2>
                <p class="header-description">Verwalten Sie alle Kontakte aus den Förderhilfen</p>
            </div>
            
            <div class="header-actions">
                <transition name="fade" mode="out-in">
                    <div class="loading-spinner-modern" v-if="isLoading('financialSupports')"></div>
                </transition>
                
                <button @click="exportContacts" class="button primary">
                    CSV Export
                </button>
            </div>
        </div>

        <div class="main-content">
            <div v-if="isLoading('financialSupports')" class="loading-state">
                <div class="loading-spinner-modern"></div>
                <p>Kontakte werden geladen...</p>
            </div>
            
            <div v-else-if="!financialSupportsWithContacts.length" class="empty-state">
                <div class="empty-icon">
                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="23 21v-2a4 4 0 0 0-3-3.87"></path>
                        <path d="16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                </div>
                <h3>Keine Kontakte gefunden</h3>
                <p>Es sind noch keine Kontakte in den Förderhilfen vorhanden.</p>
            </div>
            
            <div v-else class="contacts-grid">
                <div v-for="financialSupport in financialSupportsWithContacts" :key="financialSupport.id" class="financial-support-card">
                    <div class="card-header">
                        <div class="card-title">
                            <h3>
                                <router-link :to="`/financial-supports/${financialSupport.id}/edit`" class="support-link">
                                    {{ financialSupport.name }}
                                </router-link>
                            </h3>
                        </div>
                        <div class="card-actions">
                            <button @click="exportSingleFinancialSupportContacts(financialSupport)" class="button-red button primary">
                                CSV Export
                                <span class="contact-count-badge-inline">{{ financialSupport.totalContacts }}</span>
                            </button>
                        </div>
                    </div>
                    
                    <div class="contacts-table-container">
                        <table class="contacts-table">
                            <thead>
                                <tr>
                                    <th>Typ</th>
                                    <th>Name</th>
                                    <th>Person</th>
                                    <th>Funktion</th>
                                    <th>Abteilung</th>
                                    <th>Sprache</th>
                                    <th>E-Mail</th>
                                    <th>Telefon</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="contact in financialSupport.allContacts" :key="contact.key" class="contact-row">
                                    <td>
                                        <span class="contact-type-badge" :class="contact.type">
                                            {{ contact.type === 'person' ? 'Person' : 'Institution' }}
                                        </span>
                                    </td>
                                    <td class="contact-name">{{ contact.name || '-' }}</td>
                                    <td class="contact-person">
                                        <span v-if="contact.type === 'person' && (contact.firstName || contact.lastName)">
                                            <span v-if="contact.salutation">{{ contact.salutation === 'm' ? 'Herr' : 'Frau' }}</span>
                                            <span v-if="contact.title">{{ contact.title }}</span>
                                            <span v-if="contact.firstName">{{ contact.firstName }}</span>
                                            <span v-if="contact.lastName">{{ contact.lastName }}</span>
                                        </span>
                                        <span v-else class="text-muted">-</span>
                                    </td>
                                    <td>{{ contact.role || '-' }}</td>
                                    <td>{{ contact.department || '-' }}</td>
                                    <td>
                                        <span class="language-badge-modern" :class="contact.locale">
                                            {{ contact.locale === 'de' ? 'DE' : contact.locale === 'fr' ? 'FR' : 'IT' }}
                                        </span>
                                    </td>
                                    <td>
                                        <a v-if="contact.email" :href="'mailto:' + contact.email" class="email-link">{{ contact.email }}</a>
                                        <span v-else class="text-muted">-</span>
                                    </td>
                                    <td>{{ contact.phone || '-' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { mapGetters } from 'vuex';

export default {
    data() {
        return {
            financialSupports: [],
        };
    },
    computed: {
        ...mapGetters({
            isLoading: 'loaders/isLoading',
        }),
        financialSupportsWithContacts() {
            return this.financialSupports.filter(fs => {
                const contacts = this.getAllContacts(fs);
                return contacts.length > 0;
            }).map(fs => ({
                ...fs,
                allContacts: this.getAllContacts(fs),
                totalContacts: this.getAllContacts(fs).length
            }));
        }
    },
    methods: {
        getAllContacts(financialSupport) {
            const contacts = [];
            
            // German contacts
            if (financialSupport.contacts && financialSupport.contacts.length > 0) {
                financialSupport.contacts.forEach((contact, index) => {
                    contacts.push({
                        ...contact,
                        key: `de-${index}`,
                        locale: 'de',
                        type: contact.type || 'person'
                    });
                });
            }
            
            // French contacts
            if (financialSupport.translations && financialSupport.translations.fr && financialSupport.translations.fr.contacts) {
                financialSupport.translations.fr.contacts.forEach((contact, index) => {
                    contacts.push({
                        ...contact,
                        key: `fr-${index}`,
                        locale: 'fr',
                        type: contact.type || 'person'
                    });
                });
            }
            
            // Italian contacts
            if (financialSupport.translations && financialSupport.translations.it && financialSupport.translations.it.contacts) {
                financialSupport.translations.it.contacts.forEach((contact, index) => {
                    contacts.push({
                        ...contact,
                        key: `it-${index}`,
                        locale: 'it',
                        type: contact.type || 'person'
                    });
                });
            }
            
            return contacts;
        },
        async loadFinancialSupports() {
            try {
                const response = await fetch('/api/v1/financial-supports?limit=1000');
                const data = await response.json();
                this.financialSupports = data || [];
            } catch (error) {
                console.error('Error loading financial supports:', error);
            }
        },
        exportContacts() {
            try {
                // Get all contacts from all financial supports
                const allContacts = [];
                
                this.financialSupportsWithContacts.forEach(financialSupport => {
                    financialSupport.allContacts.forEach(contact => {
                        // Format person name
                        let personName = '';
                        if (contact.type === 'person') {
                            const nameParts = [];
                            if (contact.salutation) {
                                nameParts.push(contact.salutation === 'm' ? 'Herr' : 'Frau');
                            }
                            if (contact.title) {
                                nameParts.push(contact.title);
                            }
                            if (contact.firstName) {
                                nameParts.push(contact.firstName);
                            }
                            if (contact.lastName) {
                                nameParts.push(contact.lastName);
                            }
                            personName = nameParts.join(' ');
                        }
                        
                        // Format address
                        const addressParts = [];
                        if (contact.street) {
                            addressParts.push(contact.street);
                        }
                        if (contact.zipCode || contact.city) {
                            addressParts.push(`${contact.zipCode || ''} ${contact.city || ''}`.trim());
                        }
                        if (contact.addressSupplement) {
                            addressParts.push(contact.addressSupplement);
                        }
                        const address = addressParts.join(', ');
                        
                        allContacts.push({
                            financialSupportId: financialSupport.id,
                            financialSupportName: financialSupport.name,
                            type: contact.type === 'person' ? 'Person' : 'Institution',
                            name: contact.name || '',
                            salutation: contact.salutation === 'm' ? 'Herr' : contact.salutation === 'f' ? 'Frau' : '',
                            title: contact.title || '',
                            firstName: contact.firstName || '',
                            lastName: contact.lastName || '',
                            role: contact.role || '',
                            department: contact.department || '',
                            language: contact.locale ? contact.locale.toUpperCase() : '',
                            email: contact.email || '',
                            phone: contact.phone || '',
                            website: contact.website || '',
                            street: contact.street || '',
                            zipCode: contact.zipCode || '',
                            city: contact.city || '',
                            addressSupplement: contact.addressSupplement || ''
                        });
                    });
                });
                
                // Create CSV content
                const headers = [
                    'Förderhilfe ID',
                    'Förderhilfe',
                    'Typ',
                    'Name',
                    'Anrede',
                    'Titel',
                    'Vorname',
                    'Nachname',
                    'Funktion',
                    'Abteilung',
                    'Sprache',
                    'E-Mail',
                    'Telefon',
                    'Website',
                    'Strasse',
                    'PLZ',
                    'Ort',
                    'Adresszusatz'
                ];
                
                // Helper function to escape CSV values
                const escapeCSV = (value) => {
                    if (typeof value !== 'string') {
                        value = String(value);
                    }
                    if (value.includes(';') || value.includes('"') || value.includes('\n')) {
                        return '"' + value.replace(/"/g, '""') + '"';
                    }
                    return value;
                };
                
                // Build CSV content
                let csvContent = headers.join(';') + '\n';
                
                allContacts.forEach(contact => {
                    const row = [
                        contact.financialSupportId,
                        escapeCSV(contact.financialSupportName),
                        contact.type,
                        escapeCSV(contact.name),
                        escapeCSV(contact.salutation),
                        escapeCSV(contact.title),
                        escapeCSV(contact.firstName),
                        escapeCSV(contact.lastName),
                        escapeCSV(contact.role),
                        escapeCSV(contact.department),
                        contact.language,
                        contact.email,
                        contact.phone,
                        contact.website,
                        escapeCSV(contact.street),
                        contact.zipCode,
                        escapeCSV(contact.city),
                        escapeCSV(contact.addressSupplement)
                    ];
                    csvContent += row.join(';') + '\n';
                });
                
                // Create and download the CSV file with UTF-8 BOM for Excel compatibility
                const BOM = '\uFEFF'; // UTF-8 BOM
                const csvContentWithBOM = BOM + csvContent;
                const blob = new Blob([csvContentWithBOM], { type: 'text/csv;charset=utf-8;' });
                const link = document.createElement('a');
                const url = URL.createObjectURL(blob);
                link.setAttribute('href', url);
                link.setAttribute('download', `financial-support-contacts-${new Date().toISOString().split('T')[0]}.csv`);
                link.style.visibility = 'hidden';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(url);
                
            } catch (error) {
                console.error('Error exporting contacts:', error);
                alert('Fehler beim Exportieren der Kontakte');
            }
        },
        exportSingleFinancialSupportContacts(financialSupport) {
            try {
                // Get all contacts for this specific financial support
                const allContacts = [];
                
                financialSupport.allContacts.forEach(contact => {
                    // Format person name
                    let personName = '';
                    if (contact.type === 'person') {
                        const nameParts = [];
                        if (contact.salutation) {
                            nameParts.push(contact.salutation === 'm' ? 'Herr' : 'Frau');
                        }
                        if (contact.title) {
                            nameParts.push(contact.title);
                        }
                        if (contact.firstName) {
                            nameParts.push(contact.firstName);
                        }
                        if (contact.lastName) {
                            nameParts.push(contact.lastName);
                        }
                        personName = nameParts.join(' ');
                    }
                    
                    allContacts.push({
                        financialSupportId: financialSupport.id,
                        financialSupportName: financialSupport.name,
                        type: contact.type === 'person' ? 'Person' : 'Institution',
                        name: contact.name || '',
                        salutation: contact.salutation === 'm' ? 'Herr' : contact.salutation === 'f' ? 'Frau' : '',
                        title: contact.title || '',
                        firstName: contact.firstName || '',
                        lastName: contact.lastName || '',
                        role: contact.role || '',
                        department: contact.department || '',
                        language: contact.locale ? contact.locale.toUpperCase() : '',
                        email: contact.email || '',
                        phone: contact.phone || '',
                        website: contact.website || '',
                        street: contact.street || '',
                        zipCode: contact.zipCode || '',
                        city: contact.city || '',
                        addressSupplement: contact.addressSupplement || ''
                    });
                });
                
                // Create CSV content
                const headers = [
                    'Förderhilfe ID',
                    'Förderhilfe',
                    'Typ',
                    'Name',
                    'Anrede',
                    'Titel',
                    'Vorname',
                    'Nachname',
                    'Funktion',
                    'Abteilung',
                    'Sprache',
                    'E-Mail',
                    'Telefon',
                    'Website',
                    'Strasse',
                    'PLZ',
                    'Ort',
                    'Adresszusatz'
                ];
                
                // Helper function to escape CSV values
                const escapeCSV = (value) => {
                    if (typeof value !== 'string') {
                        value = String(value);
                    }
                    if (value.includes(';') || value.includes('"') || value.includes('\n')) {
                        return '"' + value.replace(/"/g, '""') + '"';
                    }
                    return value;
                };
                
                // Build CSV content
                let csvContent = headers.join(';') + '\n';
                
                allContacts.forEach(contact => {
                    const row = [
                        contact.financialSupportId,
                        escapeCSV(contact.financialSupportName),
                        contact.type,
                        escapeCSV(contact.name),
                        escapeCSV(contact.salutation),
                        escapeCSV(contact.title),
                        escapeCSV(contact.firstName),
                        escapeCSV(contact.lastName),
                        escapeCSV(contact.role),
                        escapeCSV(contact.department),
                        contact.language,
                        contact.email,
                        contact.phone,
                        contact.website,
                        escapeCSV(contact.street),
                        contact.zipCode,
                        escapeCSV(contact.city),
                        escapeCSV(contact.addressSupplement)
                    ];
                    csvContent += row.join(';') + '\n';
                });
                
                // Create and download the CSV file with UTF-8 BOM for Excel compatibility
                const BOM = '\uFEFF'; // UTF-8 BOM
                const csvContentWithBOM = BOM + csvContent;
                const blob = new Blob([csvContentWithBOM], { type: 'text/csv;charset=utf-8;' });
                const link = document.createElement('a');
                const url = URL.createObjectURL(blob);
                link.setAttribute('href', url);
                
                // Clean the financial support name for filename
                const cleanName = financialSupport.name.replace(/[^a-zA-Z0-9äöüÄÖÜ]/g, '-');
                link.setAttribute('download', `${cleanName}-kontakte-${new Date().toISOString().split('T')[0]}.csv`);
                link.style.visibility = 'hidden';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(url);
                
            } catch (error) {
                console.error('Error exporting single financial support contacts:', error);
                alert('Fehler beim Exportieren der Kontakte');
            }
        }
    },
    created() {
        this.loadFinancialSupports();
    }
};
</script>

<style scoped>
/* Modern Financial Support Contacts Styling */
.financial-support-contacts-modern {
    padding: 24px;
    max-width: 1400px;
    margin: 0 auto;
}

/* Page Header */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 32px;
    padding-bottom: 20px;
    border-bottom: 2px solid #f0f0f0;
}

.header-content h2 {
    margin: 0 0 8px 0;
    font-size: 1.75rem;
    font-weight: 700;
    color: #1a202c;
}

.header-description {
    margin: 0;
    color: #6c757d;
    font-size: 1rem;
}

.header-actions {
    display: flex;
    align-items: center;
    gap: 16px;
}

/* Modern Loading Spinner */
.loading-spinner-modern {
    width: 24px;
    height: 24px;
    border: 3px solid #f3f3f3;
    border-top: 3px solid #3498db;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Red Button Styles - matching FinancialSupports.vue */



.button-red.small {
    padding: 8px 12px;
    font-size: 0.85rem;
}

/* Inline Badge for Contact Count */
.contact-count-badge-inline {
    background-color: rgba(255, 255, 255, 0.3);
    color: white;
    padding: 2px 6px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 500;
    margin-left: 8px;
}

.button-red:hover .contact-count-badge-inline {
    background-color: rgba(229, 57, 64, 0.15);
    color: #E53940;
}

/* Loading State */
.loading-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 60px 20px;
    text-align: center;
}

.loading-state p {
    margin: 16px 0 0 0;
    color: #6c757d;
    font-size: 1rem;
}

/* Empty State */
.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 60px 20px;
    text-align: center;
}

.empty-icon {
    margin-bottom: 20px;
    color: #cbd5e0;
}

.empty-state h3 {
    margin: 0 0 12px 0;
    font-size: 1.3rem;
    font-weight: 600;
    color: #4a5568;
}

.empty-state p {
    margin: 0;
    color: #6c757d;
    font-size: 1rem;
}

/* Contacts Grid */
.contacts-grid {
    display: flex;
    flex-direction: column;
    gap: 24px;
}

.financial-support-card {
    background-color: #fff;
    border: 1px solid #e2e8f0;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

/* Card Header */
.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    background-color: #f8f9fa;
    border-bottom: 1px solid #e2e8f0;
}

.card-title h3 {
    margin: 0 0 8px 0;
    font-size: 1.2rem;
    font-weight: 600;
    color: #2d3748;
}

.support-link {
    color: #2d3748;
    text-decoration: none;
    transition: color 0.2s ease;
}

.support-link:hover {
    color: #3498db;
    text-decoration: none;
}


/* Table Styling */
.contacts-table-container {
    overflow-x: auto;
}

.contacts-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9rem;
}

.contacts-table th {
    background-color: #f8f9fa;
    padding: 12px 16px;
    text-align: left;
    font-weight: 600;
    color: #4a5568;
    border-bottom: 2px solid #e2e8f0;
    white-space: nowrap;
}

.contacts-table td {
    padding: 12px 16px;
    border-bottom: 1px solid #e2e8f0;
    vertical-align: top;
}

.contact-row {
    transition: background-color 0.2s ease;
}

.contact-row:hover {
    background-color: #f8f9fa;
}

.contact-name {
    font-weight: 600;
    color: #2d3748;
}

.contact-person {
    color: #6c757d;
    font-size: 0.9rem;
}

.text-muted {
    color: #a0aec0;
}

/* Contact Type Badge */
.contact-type-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.contact-type-badge.person {
    background-color: #c6f6d5;
    color: #22543d;
}

.contact-type-badge.institution {
    background-color: #bee3f8;
    color: #2a69ac;
}

/* Language Badge */
.language-badge-modern {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: white;
}

.language-badge-modern.de {
    background-color: #e53e3e;
}

.language-badge-modern.fr {
    background-color: #3182ce;
}

.language-badge-modern.it {
    background-color: #38a169;
}

/* Email Link */
.email-link {
    color: #3498db;
    text-decoration: none;
    font-weight: 500;
    transition: color 0.2s ease;
}

.email-link:hover {
    color: #2980b9;
    text-decoration: underline;
}

/* Responsive Design */
@media (max-width: 1200px) {
    .financial-support-contacts-modern {
        padding: 16px;
    }
    
    .page-header {
        flex-direction: column;
        gap: 16px;
        align-items: flex-start;
    }
    
    .header-actions {
        width: 100%;
        justify-content: flex-end;
    }
}

@media (max-width: 768px) {
    .page-header {
        text-align: center;
        align-items: center;
    }
    
    .header-actions {
        justify-content: center;
    }
    
    .card-header {
        flex-direction: column;
        gap: 12px;
        align-items: flex-start;
    }
    
    .card-actions {
        align-self: stretch;
    }
    
    .button-red {
        width: 100%;
        justify-content: center;
    }
    
    .contacts-table {
        font-size: 0.8rem;
    }
    
    .contacts-table th,
    .contacts-table td {
        padding: 8px 12px;
    }
}

@media (max-width: 640px) {
    .contacts-table-container {
        background-color: #f8f9fa;
        padding: 16px;
        border-radius: 8px;
    }
    
    .contacts-table {
        display: block;
        width: 100%;
        border: none;
    }
    
    .contacts-table thead {
        display: none;
    }
    
    .contacts-table tbody {
        display: block;
    }
    
    .contacts-table tr {
        display: block;
        background-color: #fff;
        border-radius: 8px;
        margin-bottom: 12px;
        padding: 16px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }
    
    .contacts-table td {
        display: block;
        padding: 4px 0;
        border: none;
        text-align: left;
    }
    
    .contacts-table td:before {
        content: attr(data-label);
        font-weight: 600;
        color: #4a5568;
        display: inline-block;
        width: 100px;
        margin-right: 8px;
    }
}

/* Fade Transition */
.fade-enter-active, .fade-leave-active {
    transition: opacity 0.3s ease;
}

.fade-enter, .fade-leave-to {
    opacity: 0;
}
</style>