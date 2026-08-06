/**
 * Chemins de l'application, écrits une seule fois.
 *
 * Les URL propres à une ressource (compte, tag, pointage d'une opération) sont
 * fournies par le serveur dans les payloads : elles ne sont pas reconstruites ici.
 */
export const routes = {
    dashboard: '/',
    login: '/connexion',
    register: '/inscription',
    logout: '/deconnexion',
    lock: '/verrouillage',
    accountCreate: '/nouveau-compte',
    accountStore: '/comptes',
    transactionCreate: '/ajouter',
    transactionStore: '/operations',
    recurring: '/recurrentes',
    credits: '/credits',
    bilan: '/bilan',
    closings: '/clotures',
    tags: '/tags',
    bugReports: '/signalements',
    pushSubscription: '/notifications/abonnement',
    privacy: '/confidentialite',
    widgets: '/widgets',
    account: (accountId) => `/compte/${accountId}`,
    transactionCreateFor: (accountId) => `/ajouter?compte=${accountId}`,
    tagsFor: (accountId) => `/tags?compte=${accountId}`,
};
