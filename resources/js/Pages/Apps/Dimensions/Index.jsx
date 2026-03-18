import AppLayout from '@/Layouts/AppLayout';
import { Head, useForm, usePage } from '@inertiajs/react';
import React from 'react';
import Button from '@/Components/Button';
import Modal from '@/Components/Modal';
import Input from '@/Components/Input';
import Table from '@/Components/Table';
import Search from '@/Components/Search';
import Pagination from '@/Components/Pagination';
import { IconCirclePlus, IconDatabaseOff, IconHierarchy3, IconPencilCheck, IconPencilCog, IconPlus, IconTrash, IconX } from '@tabler/icons-react';

const emptyAttribute = { key: '', label: '', type: 'text', is_required: false, options: [] };
const normalizeAttributeKey = (value = '') => value
    .toLowerCase()
    .trim()
    .replace(/[\s-]+/g, '_')
    .replace(/[^a-z0-9_]/g, '')
    .replace(/_+/g, '_');

export default function Index() {
    const { dimensions, companies, errors } = usePage().props;

    const { data, setData, post, transform } = useForm({
        id: '',
        company_id: companies[0]?.id ?? '',
        code: '',
        name: '',
        type: 'department',
        is_mandatory: false,
        attribute_schema_json: [],
        is_active: true,
        isUpdate: false,
        isOpen: false,
    });

    transform((formData) => ({
        ...formData,
        attribute_schema_json: (formData.attribute_schema_json || []).map((attribute) => ({
            ...attribute,
            key: normalizeAttributeKey(attribute?.key),
            label: attribute?.label?.trim() ?? '',
            options: attribute?.type === 'select'
                ? (attribute?.options || []).map((option) => option?.trim() ?? '').filter(Boolean)
                : [],
        })),
        _method: formData.isUpdate ? 'put' : 'post',
    }));

    const resetForm = () => {
        setData({
            id: '', company_id: companies[0]?.id ?? '', code: '', name: '', type: 'department',
            is_mandatory: false, attribute_schema_json: [], is_active: true, isUpdate: false, isOpen: false,
        });
    };

    const submit = (e) => {
        e.preventDefault();
        post(data.isUpdate ? route('apps.dimensions.update', data.id) : route('apps.dimensions.store'), { onSuccess: resetForm });
    };

    const addAttribute = () => {
        setData('attribute_schema_json', [...(data.attribute_schema_json || []), { ...emptyAttribute }]);
    };

    const removeAttribute = (index) => {
        setData('attribute_schema_json', (data.attribute_schema_json || []).filter((_, itemIndex) => itemIndex !== index));
    };

    const updateAttribute = (index, field, value) => {
        const nextAttributes = [...(data.attribute_schema_json || [])];
        nextAttributes[index] = { ...nextAttributes[index], [field]: value };
        setData('attribute_schema_json', nextAttributes);
    };

    const updateAttributeOption = (attributeIndex, optionIndex, value) => {
        const nextAttributes = [...(data.attribute_schema_json || [])];
        const options = [...(nextAttributes[attributeIndex]?.options || [])];
        options[optionIndex] = value;
        nextAttributes[attributeIndex] = { ...nextAttributes[attributeIndex], options };
        setData('attribute_schema_json', nextAttributes);
    };

    const addAttributeOption = (attributeIndex) => {
        const nextAttributes = [...(data.attribute_schema_json || [])];
        const options = [...(nextAttributes[attributeIndex]?.options || []), ''];
        nextAttributes[attributeIndex] = { ...nextAttributes[attributeIndex], options };
        setData('attribute_schema_json', nextAttributes);
    };

    const removeAttributeOption = (attributeIndex, optionIndex) => {
        const nextAttributes = [...(data.attribute_schema_json || [])];
        const options = (nextAttributes[attributeIndex]?.options || []).filter((_, itemIndex) => itemIndex !== optionIndex);
        nextAttributes[attributeIndex] = { ...nextAttributes[attributeIndex], options };
        setData('attribute_schema_json', nextAttributes);
    };

    return (
        <>
            <Head title='Dimensions' />
            <div className='mb-2 flex justify-between items-center gap-2'>
                <Button type='button' icon={<IconCirclePlus size={20} strokeWidth={1.5} />} variant='gray' label='Tambah Dimension' onClick={() => setData('isOpen', true)} />
                <div className='w-full md:w-4/12'>
                    <Search url={route('apps.dimensions.index')} placeholder='Cari dimension...' />
                </div>
            </div>

            <Modal show={data.isOpen} onClose={resetForm} title={data.isUpdate ? 'Ubah Dimension' : 'Tambah Dimension'} icon={<IconHierarchy3 size={20} strokeWidth={1.5} />}>
                <form onSubmit={submit} className='space-y-4'>
                    <div className='flex flex-col gap-2'>
                        <label className='text-gray-600 text-sm'>Company</label>
                        <select className='w-full px-3 py-1.5 border text-sm rounded-md bg-white text-gray-700 dark:bg-gray-900 dark:text-gray-300 border-gray-200 dark:border-gray-800' value={data.company_id} onChange={(e) => setData('company_id', Number(e.target.value))}>
                            {companies.map((company) => <option key={company.id} value={company.id}>{company.name}</option>)}
                        </select>
                        {errors.company_id && <small className='text-xs text-red-500'>{errors.company_id}</small>}
                    </div>
                    <div className='grid grid-cols-2 gap-3'>
                        <Input label='Kode' type='text' value={data.code} onChange={(e) => setData('code', e.target.value)} errors={errors.code} />
                        <Input label='Nama' type='text' value={data.name} onChange={(e) => setData('name', e.target.value)} errors={errors.name} />
                    </div>
                    <div className='grid grid-cols-2 gap-3'>
                        <div className='flex flex-col gap-2'>
                            <label className='text-gray-600 text-sm'>Tipe</label>
                            <select className='w-full px-3 py-1.5 border text-sm rounded-md bg-white text-gray-700 dark:bg-gray-900 dark:text-gray-300 border-gray-200 dark:border-gray-800' value={data.type} onChange={(e) => setData('type', e.target.value)}>
                                {['branch', 'department', 'cost_center', 'project', 'customer', 'vendor', 'employee', 'custom'].map((type) => <option key={type} value={type}>{type}</option>)}
                            </select>
                        </div>
                        <div className='flex flex-col gap-2'>
                            <label className='text-gray-600 text-sm'>Mandatory</label>
                            <select className='w-full px-3 py-1.5 border text-sm rounded-md bg-white text-gray-700 dark:bg-gray-900 dark:text-gray-300 border-gray-200 dark:border-gray-800' value={data.is_mandatory ? '1' : '0'} onChange={(e) => setData('is_mandatory', e.target.value === '1')}>
                                <option value='0'>Tidak</option><option value='1'>Ya</option>
                            </select>
                        </div>
                    </div>
                    <div className='space-y-3'>
                        <div className='flex justify-between items-center'>
                            <label className='text-gray-600 text-sm'>Custom Atribut per Dimension</label>
                            <Button type='button' variant='blue' icon={<IconPlus size={16} strokeWidth={1.5} />} label='Tambah Atribut' onClick={addAttribute} />
                        </div>
                        <div className='space-y-3'>
                            {(data.attribute_schema_json || []).length === 0 && (
                                <div className='text-xs text-gray-500'>Belum ada atribut custom. Klik Tambah Atribut untuk menambahkan.</div>
                            )}
                            {(data.attribute_schema_json || []).map((attribute, attributeIndex) => (
                                <div key={attributeIndex} className='border border-gray-200 dark:border-gray-800 rounded-md p-3 space-y-2'>
                                    <div className='grid grid-cols-1 md:grid-cols-12 gap-2 items-end'>
                                        <div className='md:col-span-3'>
                                            <Input
                                                label='Field Key'
                                                type='text'
                                                value={attribute.key ?? ''}
                                                onChange={(e) => updateAttribute(attributeIndex, 'key', e.target.value)}
                                                errors={errors[`attribute_schema_json.${attributeIndex}.key`]}
                                            />
                                        </div>
                                        <div className='md:col-span-4'>
                                            <Input
                                                label='Label'
                                                type='text'
                                                value={attribute.label ?? ''}
                                                onChange={(e) => updateAttribute(attributeIndex, 'label', e.target.value)}
                                                errors={errors[`attribute_schema_json.${attributeIndex}.label`]}
                                            />
                                        </div>
                                        <div className='md:col-span-3'>
                                            <label className='text-gray-600 text-sm'>Tipe Field</label>
                                            <select className='w-full px-3 py-1.5 border text-sm rounded-md bg-white text-gray-700 dark:bg-gray-900 dark:text-gray-300 border-gray-200 dark:border-gray-800' value={attribute.type ?? 'text'} onChange={(e) => updateAttribute(attributeIndex, 'type', e.target.value)}>
                                                {['text', 'number', 'date', 'boolean', 'select'].map((fieldType) => <option key={fieldType} value={fieldType}>{fieldType}</option>)}
                                            </select>
                                            {errors[`attribute_schema_json.${attributeIndex}.type`] && <small className='text-xs text-red-500'>{errors[`attribute_schema_json.${attributeIndex}.type`]}</small>}
                                        </div>
                                        <div className='md:col-span-1'>
                                            <label className='text-gray-600 text-sm'>Wajib</label>
                                            <select className='w-full px-3 py-1.5 border text-sm rounded-md bg-white text-gray-700 dark:bg-gray-900 dark:text-gray-300 border-gray-200 dark:border-gray-800' value={attribute.is_required ? '1' : '0'} onChange={(e) => updateAttribute(attributeIndex, 'is_required', e.target.value === '1')}>
                                                <option value='0'>Tidak</option>
                                                <option value='1'>Ya</option>
                                            </select>
                                        </div>
                                        <div className='md:col-span-1 pb-1'>
                                            <Button type='button' variant='rose' icon={<IconTrash size={16} strokeWidth={1.5} />} onClick={() => removeAttribute(attributeIndex)} />
                                        </div>
                                    </div>
                                    {attribute.type === 'select' && (
                                        <div className='space-y-2'>
                                            <div className='flex justify-between items-center'>
                                                <small className='text-xs text-gray-500'>Pilihan (options)</small>
                                                <Button type='button' variant='gray' icon={<IconPlus size={14} strokeWidth={1.5} />} label='Tambah Opsi' onClick={() => addAttributeOption(attributeIndex)} />
                                            </div>
                                            <div className='space-y-2'>
                                                {(attribute.options || []).map((option, optionIndex) => (
                                                    <div key={optionIndex} className='flex gap-2 items-center'>
                                                        <div className='w-full space-y-1'>
                                                            <input type='text' className='w-full px-3 py-1.5 border text-sm rounded-md bg-white text-gray-700 dark:bg-gray-900 dark:text-gray-300 border-gray-200 dark:border-gray-800' value={option} onChange={(e) => updateAttributeOption(attributeIndex, optionIndex, e.target.value)} placeholder='Contoh: Retail / Corporate' />
                                                            {errors[`attribute_schema_json.${attributeIndex}.options.${optionIndex}`] && (
                                                                <small className='text-xs text-red-500'>{errors[`attribute_schema_json.${attributeIndex}.options.${optionIndex}`]}</small>
                                                            )}
                                                        </div>
                                                        <Button type='button' variant='rose' icon={<IconX size={14} strokeWidth={1.5} />} onClick={() => removeAttributeOption(attributeIndex, optionIndex)} />
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    )}
                                </div>
                            ))}
                        </div>
                        {errors.attribute_schema_json && <small className='text-xs text-red-500'>{errors.attribute_schema_json}</small>}
                    </div>
                    <div className='flex flex-col gap-2'>
                        <label className='text-gray-600 text-sm'>Status</label>
                        <select className='w-full px-3 py-1.5 border text-sm rounded-md bg-white text-gray-700 dark:bg-gray-900 dark:text-gray-300 border-gray-200 dark:border-gray-800' value={data.is_active ? '1' : '0'} onChange={(e) => setData('is_active', e.target.value === '1')}>
                            <option value='1'>Aktif</option><option value='0'>Nonaktif</option>
                        </select>
                    </div>
                    <Button type='submit' variant='gray' icon={<IconPencilCheck size={20} strokeWidth={1.5} />} label='Simpan' />
                </form>
            </Modal>

            <Table.Card title='Data Dimensions'>
                <Table>
                    <Table.Thead><tr><Table.Th>No</Table.Th><Table.Th>Company</Table.Th><Table.Th>Kode</Table.Th><Table.Th>Nama</Table.Th><Table.Th>Tipe</Table.Th><Table.Th>Status</Table.Th><Table.Th className='w-40'></Table.Th></tr></Table.Thead>
                    <Table.Tbody>
                        {dimensions.data.length ? dimensions.data.map((dimension, i) => (
                            <tr key={dimension.id} className='hover:bg-gray-100 dark:hover:bg-gray-900'>
                                <Table.Td>{i + 1 + ((dimensions.current_page - 1) * dimensions.per_page)}</Table.Td>
                                <Table.Td>{dimension.company?.name}</Table.Td><Table.Td>{dimension.code}</Table.Td><Table.Td>{dimension.name}</Table.Td>
                                <Table.Td className='capitalize'>{dimension.type}</Table.Td><Table.Td>{dimension.is_active ? 'Aktif' : 'Nonaktif'}</Table.Td>
                                <Table.Td><div className='flex gap-2'>
                                    <Button type='modal' variant='orange' icon={<IconPencilCog size={16} strokeWidth={1.5} />} onClick={() => setData({ ...dimension, attribute_schema_json: dimension.attribute_schema_json ?? [], isUpdate: true, isOpen: true })} />
                                    <Button type='delete' variant='rose' icon={<IconTrash size={16} strokeWidth={1.5} />} url={route('apps.dimensions.destroy', dimension.id)} />
                                </div></Table.Td>
                            </tr>
                        )) : <Table.Empty colSpan={7} message={<><div className='flex justify-center mb-2'><IconDatabaseOff size={24} /></div><span>Data dimension tidak ditemukan.</span></>} />}
                    </Table.Tbody>
                </Table>
            </Table.Card>
            {dimensions.last_page !== 1 && <Pagination links={dimensions.links} />}
        </>
    );
}

Index.layout = (page) => <AppLayout children={page} />;
