import { describe, expect, it } from 'vitest'
import type { WooProduct } from '~/types/woocommerce'
import {
  decodeHtml,
  extractAuthor,
  formatWooPrice,
  getProductKind,
  mapWooProduct,
  productMatchesKind,
  stripHtml
} from '~/utils/products'

const baseProduct: WooProduct = {
  id: 1,
  name: 'Women&#8217;s Work',
  slug: 'womens-work',
  permalink: 'https://apfpress.com/product/womens-work/',
  short_description: '<p>Women&#8217;s Work<br />Author: Merle A. Jacobs</p>',
  description: '',
  prices: {
    price: '999',
    regular_price: '999',
    sale_price: '999',
    currency_code: 'CAD',
    currency_symbol: '$',
    currency_minor_unit: 2,
    currency_decimal_separator: '.',
    currency_thousand_separator: ',',
    currency_prefix: '$',
    currency_suffix: ''
  },
  price_html: '',
  images: [
    {
      id: 10,
      src: 'https://example.com/book.jpg',
      alt: ''
    }
  ],
  categories: [
    {
      id: 30,
      name: 'E-Books',
      slug: 'e-books'
    }
  ],
  is_purchasable: true,
  is_in_stock: true
}

describe('product utilities', () => {
  it('decodes and strips WooCommerce HTML snippets', () => {
    expect(decodeHtml('Women&#8217;s Work &amp; Justice')).toBe("Women's Work & Justice")
    expect(stripHtml('<p>Title<br />by Jane Doe</p>')).toBe('Title\nby Jane Doe')
  })

  it('extracts common author line formats', () => {
    expect(extractAuthor('<p>Book Title<br />Author: Merle A. Jacobs</p>')).toBe('Merle A. Jacobs')
    expect(extractAuthor('<p>Book Title<br />by Wallace E. Northover</p>')).toBe('Wallace E. Northover')
    expect(extractAuthor('<p>Book Title<br />Subtitle<br />Edited by: L. A. Visano</p>')).toBe('L. A. Visano')
  })

  it('identifies print and ebook products by WooCommerce categories', () => {
    expect(getProductKind(baseProduct.categories)).toBe('ebook')
    expect(productMatchesKind(baseProduct, 'ebook')).toBe(true)
    expect(productMatchesKind(baseProduct, 'print')).toBe(false)
  })

  it('formats purchasable CAD prices and maps cards', () => {
    expect(formatWooPrice(baseProduct)).toBe('$9.99')

    const card = mapWooProduct(baseProduct)

    expect(card.title).toBe("Women's Work")
    expect(card.price).toBe('$9.99')
    expect(card.url).toBe('https://apfpress.com/product/womens-work/')
    expect(card.kind).toBe('ebook')
  })
})
