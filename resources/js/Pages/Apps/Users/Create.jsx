import React from 'react'
import { Head, usePage, useForm } from '@inertiajs/react'
import Card from '@/Components/Card'
import AppLayout from '@/Layouts/AppLayout'
import { IconUsersPlus, IconPencilPlus, IconUserShield } from '@tabler/icons-react'
import Input from '@/Components/Input'
import Button from '@/Components/Button'
import Checkbox from '@/Components/Checkbox'
import toast from 'react-hot-toast'
export default function Create() {
    // destruct props roles from use page
    const { roles, companies, auth } = usePage().props;

    const {data, setData, post, errors} = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        selectedRoles: [],
        company_id: '',
    });

    const setSelectedRoles = (e) => {
        let items = data.selectedRoles

        items.push(e.target.value)

        setData('selectedRoles', items);
    }

    const saveUser = async (e) => {
        e.preventDefault();

        post(route('apps.users.store'), {
            onSuccess: () => {
                toast('Data berhasil disimpan', {
                    icon: '👏',
                    style: {
                        borderRadius: '10px',
                        background: '#1C1F29',
                        color: '#fff',
                    },
                })
            }
        });
    }

    return (
        <>
            <Head title={'Tambah Data Pengguna'}/>
            <Card
                title={'Tambah Data Pengguna'}
                icon={<IconUsersPlus size={20} strokeWidth={1.5}/>}
                footer={
                    <Button
                        type={'submit'}
                        label={'Simpan'}
                        icon={<IconPencilPlus size={20} strokeWidth={1.5}/>}
                        variant={'gray'}
                    />
                }
                form={saveUser}
            >
                <div className='mb-4 flex flex-col md:flex-row justify-between gap-4'>
                    <div className='w-full md:w-1/2'>
                        <Input
                            type={'text'}
                            label={'Nama Pengguna'}
                            value={data.name}
                            onChange={e => setData('name', e.target.value)}
                            errors={errors.name}
                        />
                    </div>
                    <div className='w-full md:w-1/2'>
                        <Input
                            type={'email'}
                            label={'Email Pengguna'}
                            value={data.email}
                            onChange={e => setData('email', e.target.value)}
                            errors={errors.email}
                        />
                    </div>
                </div>
                <div className='mb-4 flex flex-col md:flex-row gap-4'>
                    <div className='w-full md:w-1/2'>
                        <Input
                            type={'password'}
                            label={'Kata Sandi'}
                            value={data.password}
                            onChange={e => setData('password', e.target.value)}
                            errors={errors.password}
                        />
                    </div>
                    <div className='w-full md:w-1/2'>
                        <Input
                            type={'password'}
                            label={'Konfirmasi Kata Sandi'}
                            value={data.password_confirmation}
                            onChange={e => setData('password_confirmation', e.target.value)}
                        />
                    </div>
                </div>
                <div className='mb-4'>
                    <label className='text-sm font-medium text-gray-700 dark:text-gray-300'>Company (Opsional, wajib untuk company-admin)</label>
                    <select className='mt-1 w-full px-3 py-1.5 border text-sm rounded-md bg-white text-gray-700 dark:bg-gray-900 dark:text-gray-300 border-gray-200 dark:border-gray-800' value={data.company_id} onChange={e => setData('company_id', e.target.value ? Number(e.target.value) : '')}>
                        <option value=''>- Pilih Company -</option>
                        {companies.map((company) => <option key={company.id} value={company.id}>{company.name}</option>)}
                    </select>
                    {errors.company_id && <div className='text-xs text-red-500 mt-1'>{errors.company_id}</div>}
                </div>
                <div className={`p-4 rounded-t-lg border bg-white dark:bg-gray-950 dark:border-gray-900`}>
                    <div className='flex items-center gap-2 font-semibold text-sm text-gray-700 dark:text-gray-400'>
                        Akses Group
                    </div>
                </div>
                <div className='p-4 rounded-b-lg border border-t-0 bg-gray-100 dark:bg-gray-900 dark:border-gray-900'>
                    <div className='flex flex-row flex-wrap gap-4'>
                        {roles.filter((role) => auth?.super || role.name !== 'company-admin').map((role, i) => (
                            <Checkbox label={role.name} value={role.name} onChange={setSelectedRoles} key={i}/>
                        ))}
                    </div>
                    {errors.selectedRoles && <div className='text-xs text-red-500 mt-4'>{errors.selectedRoles}</div>}
                </div>
            </Card>
        </>
    )
}

Create.layout = page => <AppLayout children={page}/>
