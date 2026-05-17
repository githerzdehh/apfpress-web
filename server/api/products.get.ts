import { setDefaultResultOrder } from 'node:dns'
import type { ProductKind } from '~/utils/products'
import type { WooProduct } from '~/types/woocommerce'
import { mapWooProduct, productMatchesKind, sortProductsByTitle } from '~/utils/products'

setDefaultResultOrder('ipv4first')

export default defineEventHandler(async (event) => {
  const config = useRuntimeConfig()
  const query = getQuery(event)
  const kind = typeof query.kind === 'string' ? query.kind : 'all'
  const safeKind: ProductKind = ['all', 'print', 'ebook'].includes(kind) ? kind as ProductKind : 'all'

  const products = await $fetch<WooProduct[]>(`${config.public.wpBaseUrl}/wp-json/wc/store/v1/products`, {
    query: {
      per_page: 100
    },
    headers: {
      Accept: 'application/json'
    }
  })

  return sortProductsByTitle(
    products
      .filter((product) => productMatchesKind(product, safeKind))
      .map(mapWooProduct)
  )
})
