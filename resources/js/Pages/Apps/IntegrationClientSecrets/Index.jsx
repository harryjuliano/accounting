import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/Button';
import Input from '@/Components/Input';
import Modal from '@/Components/Modal';
import Pagination from '@/Components/Pagination';
import Table from '@/Components/Table';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { IconDatabaseOff, IconPencilCog, IconPlus, IconShieldLock, IconTrash } from '@tabler/icons-react';
import React, { useMemo } from 'react';

const initialState = (companies) => ({
    id: '',
    company_id: companies[0]?.id ?? '',
    branch_id: companies[0]?.branches?.[0]?.id ?? '',
    source_module: 'inventory',
    client_name: '',
    client_secret: '',
    isUpdate: false,
    isOpen: false,
});

export default function Index() {
    const { companies, credentials, generatedCredential, integrationTableReady, errors } = usePage().props;

    const { data, setData, post, processing } = useForm(initialState(companies));

    const availableBranches = useMemo(() => {
        const selectedCompany = companies.find((company) => Number(company.id) === Number(data.company_id));

        return selectedCompany?.branches ?? [];
    }, [companies, data.company_id]);

    const resetForm = () => setData(initialState(companies));

    const openCreateModal = () => {
        setData({ ...initialState(companies), isOpen: true });
    };

    const openEditModal = (credential) => {
        setData({
            id: credential.id,
            company_id: credential.company_id,
            branch_id: credential.branch_id,
            source_module: credential.source_module,
            client_name: credential.client_name ?? '',
            client_secret: '',
            isUpdate: true,
            isOpen: true,
        });
    };

    const submit = (e) => {
        e.preventDefault();

        post(data.isUpdate ? route('apps.integration-client-secrets.update', data.id) : route('apps.integration-client-secrets.store'), {
            onSuccess: resetForm,
            data: {
                ...data,
                _method: data.isUpdate ? 'put' : 'post',
            },
        });
    };

    const toggleStatus = (credential) => {
        router.patch(route('apps.integration-client-secrets.toggle-status', credential.id));
    };

    return (
        <>
            <Head title="Client Secret" />

            {!integrationTableReady && (
                <div className="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:bg-amber-950/20 dark:border-amber-900 dark:text-amber-300">
                    Tabel <span className="font-mono">integration_client_credentials</span> belum tersedia. Silakan jalankan migrasi database dulu.
                </div>
            )}

            {errors.integration && (
                <div className="mb-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:bg-red-950/20 dark:border-red-900 dark:text-red-300">{errors.integration}</div>
            )}

            <div className="mb-4 flex justify-end">
                <Button
                    type="button"
                    variant="gray"
                    icon={<IconPlus size={18} strokeWidth={1.5} />}
                    label="Tambah Client Key"
                    onClick={openCreateModal}
                    disabled={!integrationTableReady}
                />
            </div>

            <Modal show={data.isOpen} onClose={resetForm} title={data.isUpdate ? 'Edit Client Credential' : 'Tambah Client Credential'}>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div className="flex flex-col gap-2">
                            <label className="text-gray-600 text-sm">Company</label>
                            <select
                                className="w-full px-3 py-1.5 border text-sm rounded-md bg-white text-gray-700 dark:bg-gray-900 dark:text-gray-300 border-gray-200 dark:border-gray-800"
                                value={data.company_id}
                                onChange={(e) => {
                                    const companyId = Number(e.target.value);
                                    const selectedCompany = companies.find((company) => Number(company.id) === companyId);
                                    setData({
                                        ...data,
                                        company_id: companyId,
                                        branch_id: selectedCompany?.branches?.[0]?.id ?? '',
                                    });
                                }}
                            >
                                {companies.map((company) => (
                                    <option key={company.id} value={company.id}>{company.name}</option>
                                ))}
                            </select>
                            {errors.company_id && <small className="text-xs text-red-500">{errors.company_id}</small>}
                        </div>

                        <div className="flex flex-col gap-2">
                            <label className="text-gray-600 text-sm">Branch</label>
                            <select
                                className="w-full px-3 py-1.5 border text-sm rounded-md bg-white text-gray-700 dark:bg-gray-900 dark:text-gray-300 border-gray-200 dark:border-gray-800"
                                value={data.branch_id}
                                onChange={(e) => setData('branch_id', Number(e.target.value))}
                            >
                                {availableBranches.length ? availableBranches.map((branch) => (
                                    <option key={branch.id} value={branch.id}>{branch.name}</option>
                                )) : <option value="">Tidak ada branch aktif</option>}
                            </select>
                            {errors.branch_id && <small className="text-xs text-red-500">{errors.branch_id}</small>}
                        </div>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <Input
                            label="Source Module"
                            type="text"
                            placeholder="Contoh: inventory"
                            value={data.source_module}
                            onChange={(e) => setData('source_module', e.target.value.toLowerCase())}
                            errors={errors.source_module}
                        />
                        <Input
                            label="Nama Client"
                            type="text"
                            placeholder="Contoh: POS Jakarta"
                            value={data.client_name}
                            onChange={(e) => setData('client_name', e.target.value)}
                            errors={errors.client_name}
                        />
                    </div>

                    <Input
                        label={data.isUpdate ? 'Client Secret Baru (opsional)' : 'Client Secret (opsional)'}
                        type="text"
                        placeholder={data.isUpdate ? 'Isi jika ingin mengganti secret' : 'Kosongkan untuk auto-generate'}
                        value={data.client_secret}
                        onChange={(e) => setData('client_secret', e.target.value)}
                        errors={errors.client_secret}
                    />

                    <Button
                        type="submit"
                        variant="gray"
                        icon={<IconPlus size={18} strokeWidth={1.5} />}
                        label={processing ? 'Menyimpan...' : 'Simpan'}
                        disabled={processing || !availableBranches.length || !companies.length || !integrationTableReady}
                    />
                </form>
            </Modal>

            {generatedCredential?.client_secret && (
                <div className="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 p-4 dark:bg-emerald-950/20 dark:border-emerald-900">
                    <div className="flex items-center gap-2 mb-2 text-emerald-700 dark:text-emerald-300 font-semibold">
                        <IconShieldLock size={18} strokeWidth={1.5} />
                        Credential tersimpan
                    </div>
                    <div className="text-sm text-emerald-900 dark:text-emerald-200 space-y-1">
                        <p><span className="font-semibold">Client Key:</span> {generatedCredential.client_key}</p>
                        <p><span className="font-semibold">Client Secret:</span> {generatedCredential.client_secret}</p>
                    </div>
                </div>
            )}

            <Table.Card title="Daftar Client Credential">
                <Table>
                    <Table.Thead>
                        <tr>
                            <Table.Th>No</Table.Th>
                            <Table.Th>Client Name</Table.Th>
                            <Table.Th>Client Key</Table.Th>
                            <Table.Th>Client Secret</Table.Th>
                            <Table.Th>Source Module</Table.Th>
                            <Table.Th>Company / Branch</Table.Th>
                            <Table.Th>Status</Table.Th>
                            <Table.Th>Last Used</Table.Th>
                            <Table.Th className="w-40"></Table.Th>
                        </tr>
                    </Table.Thead>
                    <Table.Tbody>
                        {credentials.data.map((credential, index) => (
                            <tr key={credential.id} className="hover:bg-gray-100 dark:hover:bg-gray-900">
                                <Table.Td>{index + 1 + ((credentials.current_page - 1) * credentials.per_page)}</Table.Td>
                                <Table.Td>{credential.client_name || '-'}</Table.Td>
                                <Table.Td className="font-mono text-xs">{credential.client_key}</Table.Td>
                                <Table.Td className="font-mono text-xs">******** (hashed)</Table.Td>
                                <Table.Td className="uppercase">{credential.source_module}</Table.Td>
                                <Table.Td>{credential.company?.name} / {credential.branch?.name}</Table.Td>
                                <Table.Td>
                                    <button
                                        type="button"
                                        className={`rounded-full px-2 py-1 text-xs ${credential.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-200 text-gray-700'}`}
                                        onClick={() => toggleStatus(credential)}
                                    >
                                        {credential.is_active ? 'Aktif' : 'Nonaktif'}
                                    </button>
                                </Table.Td>
                                <Table.Td>{credential.last_used_at ? new Date(credential.last_used_at).toLocaleString('id-ID') : '-'}</Table.Td>
                                <Table.Td>
                                    <div className="flex gap-2">
                                        <Button type="modal" variant="orange" icon={<IconPencilCog size={16} strokeWidth={1.5} />} onClick={() => openEditModal(credential)} />
                                        <Button type="delete" variant="rose" icon={<IconTrash size={16} strokeWidth={1.5} />} url={route('apps.integration-client-secrets.destroy', credential.id)} />
                                    </div>
                                </Table.Td>
                            </tr>
                        ))}
                        {!credentials.data.length && (
                            <Table.Empty
                                colSpan={9}
                                message={<div className="flex items-center justify-center gap-2"><IconDatabaseOff size={18} />Belum ada credential integration.</div>}
                            />
                        )}
                    </Table.Tbody>
                </Table>
            </Table.Card>
            {credentials.last_page > 1 && <Pagination links={credentials.links} />}
        </>
    );
}

Index.layout = (page) => <AppLayout children={page} />;
