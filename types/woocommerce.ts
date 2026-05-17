export interface WooCategory {
  id: number
  name: string
  slug: string
  link?: string
}

export interface WooImage {
  id: number
  src: string
  thumbnail?: string
  srcset?: string
  sizes?: string
  name?: string
  alt?: string
}

export interface WooPrices {
  price: string
  regular_price: string
  sale_price: string
  currency_code: string
  currency_symbol: string
  currency_minor_unit: number
  currency_decimal_separator: string
  currency_thousand_separator: string
  currency_prefix: string
  currency_suffix: string
}

export interface WooProduct {
  id: number
  name: string
  slug: string
  permalink: string
  short_description: string
  description: string
  prices: WooPrices
  price_html: string
  images: WooImage[]
  categories: WooCategory[]
  is_purchasable: boolean
  is_in_stock: boolean
}

export interface ProductCardData {
  id: number
  title: string
  slug: string
  url: string
  excerpt: string
  author: string
  price: string
  image: string
  imageAlt: string
  categories: WooCategory[]
  isPurchasable: boolean
  isInStock: boolean
  kind: 'print' | 'ebook' | 'uncategorized'
}
