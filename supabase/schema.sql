-- Supabase Schema: core tables and RLS policies
-- NOTE: Run this SQL in Supabase project (SQL editor)

-- Extensions (ensure uuid generation if needed)
-- create extension if not exists "pgcrypto";

-- Users table mapped to auth.users via id
create table if not exists public.app_user (
  id uuid primary key, -- matches auth.users.id
  email text,
  full_name text,
  phone_number text,
  national_id text,
  address text,
  account_number text,
  id_photo_front_url text,
  id_photo_back_url text,
  latitude double precision,
  longitude double precision,
  is_active boolean default true,
  role text default 'user' check (role in ('user','admin','agent')),
  created_at timestamptz default now()
);
alter table public.app_user enable row level security;

-- Wallets: one per user
create table if not exists public.wallet (
  id uuid primary key default gen_random_uuid(),
  user_id uuid not null references public.app_user(id) on delete cascade,
  balance numeric(12,2) not null default 0,
  currency text not null default 'EGP',
  last_updated timestamptz not null default now(),
  unique(user_id)
);
alter table public.wallet enable row level security;

-- Transactions
create table if not exists public.transaction (
  id uuid primary key default gen_random_uuid(),
  sender_id uuid not null references public.app_user(id),
  receiver_id uuid references public.app_user(id),
  amount numeric(12,2) not null check (amount >= 0),
  type text not null, -- transfer, deposit, withdraw, service
  status text not null default 'completed', -- pending, completed, failed
  note text,
  created_at timestamptz not null default now()
);
alter table public.transaction enable row level security;

-- Services catalog
create table if not exists public.service (
  id uuid primary key default gen_random_uuid(),
  name text not null,
  category text,
  price numeric(12,2) not null default 0,
  fields jsonb, -- dynamic fields definition [{key,label}...]
  provider_id uuid references public.app_user(id),
  enabled boolean not null default true
);
alter table public.service enable row level security;

-- Service requests/orders
create table if not exists public.service_request (
  id uuid primary key default gen_random_uuid(),
  service_id uuid not null references public.service(id) on delete cascade,
  user_id uuid not null references public.app_user(id) on delete cascade,
  fields jsonb,
  price numeric(12,2) not null default 0,
  status text not null default 'pending',
  created_at timestamptz not null default now()
);
alter table public.service_request enable row level security;

-- Deposit methods
create table if not exists public.deposit_method (
  id uuid primary key default gen_random_uuid(),
  name text not null unique,
  sort_order int not null default 0,
  enabled boolean not null default true
);
alter table public.deposit_method enable row level security;

-- Deposits
create table if not exists public.deposit (
  id uuid primary key default gen_random_uuid(),
  user_id uuid not null references public.app_user(id) on delete cascade,
  method text not null,
  amount numeric(12,2) not null check (amount >= 0),
  transaction_number text,
  sender_phone text,
  receipt_path text,
  status text not null default 'pending',
  note text,
  created_at timestamptz not null default now()
);
alter table public.deposit enable row level security;

-- Helper: check if current user is admin/agent
create or replace view public.current_role as
select au.id as user_id, au.role
from public.app_user au
where au.id = auth.uid();

-- RLS Policies
-- app_user
create policy "user_select_self" on public.app_user
  for select using (id = auth.uid());
create policy "user_update_self" on public.app_user
  for update using (id = auth.uid());
create policy "admin_all_app_user" on public.app_user
  for all using (exists (select 1 from public.app_user x where x.id = auth.uid() and x.role = 'admin'));

-- wallet
create policy "wallet_owner_select" on public.wallet
  for select using (user_id = auth.uid());
create policy "wallet_owner_update" on public.wallet
  for update using (user_id = auth.uid());
create policy "admin_all_wallet" on public.wallet
  for all using (exists (select 1 from public.app_user x where x.id = auth.uid() and x.role = 'admin'));

-- transaction
create policy "tx_user_select" on public.transaction
  for select using (sender_id = auth.uid() or receiver_id = auth.uid());
create policy "tx_user_insert" on public.transaction
  for insert with check (sender_id = auth.uid());
create policy "admin_all_tx" on public.transaction
  for all using (exists (select 1 from public.app_user x where x.id = auth.uid() and x.role = 'admin'));

-- service
create policy "service_public_select" on public.service
  for select using (enabled = true);
create policy "admin_all_service" on public.service
  for all using (exists (select 1 from public.app_user x where x.id = auth.uid() and x.role = 'admin'));

-- service_request
create policy "sr_user_select" on public.service_request
  for select using (user_id = auth.uid());
create policy "sr_user_insert" on public.service_request
  for insert with check (user_id = auth.uid());
create policy "admin_all_sr" on public.service_request
  for all using (exists (select 1 from public.app_user x where x.id = auth.uid() and x.role = 'admin'));

-- deposit_method
create policy "dm_public_select" on public.deposit_method
  for select using (enabled = true or exists (select 1 from public.app_user x where x.id = auth.uid() and x.role = 'admin'));
create policy "admin_all_dm" on public.deposit_method
  for all using (exists (select 1 from public.app_user x where x.id = auth.uid() and x.role = 'admin'));

-- deposit
create policy "dep_user_select" on public.deposit
  for select using (user_id = auth.uid());
create policy "dep_user_insert" on public.deposit
  for insert with check (user_id = auth.uid());
create policy "admin_all_dep" on public.deposit
  for all using (exists (select 1 from public.app_user x where x.id = auth.uid() and x.role = 'admin'));

-- Seed example deposit methods
insert into public.deposit_method (name, sort_order, enabled)
values ('Vodafone Cash', 1, true),
       ('Etisalat Cash', 2, true),
       ('Orange Cash', 3, true)
on conflict (name) do nothing;
