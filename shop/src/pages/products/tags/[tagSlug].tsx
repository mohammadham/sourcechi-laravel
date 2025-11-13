import { serverSideTranslations } from 'next-i18next/serverSideTranslations';
import { useTranslation } from 'next-i18next';
import type { NextPageWithLayout, ProductQueryOptions, Tag } from '@/types';
import type {
  GetStaticPaths,
  GetStaticProps,
  InferGetStaticPropsType,
} from 'next';
import Grid from '@/components/product/grid';
import client from '@/data/client';
import { API_ENDPOINTS } from '@/data/client/endpoints';
import { useProducts } from '@/data/product';
import Layout from '@/layouts/_layout';
import { dehydrate, QueryClient } from 'react-query';
import invariant from 'tiny-invariant';

// This function gets called at build time
type ParsedQueryParams = {
  tagSlug: string;
};

export const getStaticPaths: GetStaticPaths<ParsedQueryParams> = async ({
  locales,
}) => {
  invariant(locales, 'locales is not defined');
  
  try {
    // Fetch all tags (no language filter)
    const { data: tags } = await client.tags.all({ limit: 200 });
    
    const paths: Array<{ params: { tagSlug: string }; locale: string }> = [];

    // For each tag, check if it has products in each locale
    for (const tag of tags || []) {
      // For each locale, check if there are products with this tag
      for (const locale of locales || []) {
        try {
          // Check if tag has products in this locale
          const products = await client.products.all({
            tags: tag.slug,
            language: locale,
            limit: 1, // We just need to know if any product exists
          });
          
          // Only add path if products exist
          if (products?.data && products.data.length > 0) {
            paths.push({
              params: { tagSlug: tag.slug },
              locale: locale,
            });
          }
        } catch (error) {
          // If error, skip this tag-locale combination
          console.warn(`Skipping tag ${tag.slug} for locale ${locale}:`, error);
        }
      }
    }

    console.log(`[getStaticPaths:tags] Generated ${paths.length} paths for ${tags?.length || 0} tags`);

    return {
      paths: paths || [],
      fallback: 'blocking', // Enable ISR for new tags
    };
  } catch (error) {
    console.warn('Failed to fetch tags during build:', error);
    return {
      paths: [],
      fallback: 'blocking',
    };
  }
};

type PageProps = {
  tag: Tag;
};
export const getStaticProps: GetStaticProps<
  PageProps,
  ParsedQueryParams
> = async ({ params, locale }) => {
  const queryClient = new QueryClient();
  const { tagSlug } = params!; //* we know it's required because of getStaticPaths
  try {
    const [tag, products] = await Promise.all([
      client.tags.get({ slug: tagSlug, language: locale }),
      client.products.all({ tags: tagSlug, language: locale, limit: 1 }),
    ]);

    // Check if tag has products in this locale
    if (!products?.data || products.data.length === 0) {
      console.warn(`[getStaticProps:tags] Tag ${tagSlug} has no products in locale ${locale}`);
      return {
        notFound: true,
      };
    }

    // Prefetch products for infinite scroll
    await queryClient.prefetchInfiniteQuery(
      [API_ENDPOINTS.PRODUCTS, { tags: tagSlug, language: locale }],
      ({ queryKey }) =>
        client.products.all(queryKey[1] as ProductQueryOptions)
    );

    return {
      props: {
        tag,
        dehydratedState: JSON.parse(JSON.stringify(dehydrate(queryClient))),
        ...(await serverSideTranslations(locale!, ['common'])),
      },
      revalidate: 60, // In seconds
    };
  } catch (error) {
    //* if we get here, the tag doesn't exist or something else went wrong
    console.error(`[getStaticProps:tags] Error for ${tagSlug} in ${locale}:`, error);
    return {
      notFound: true,
    };
  }
};
const TagPage: NextPageWithLayout<
  InferGetStaticPropsType<typeof getStaticProps>
> = ({ tag }) => {
  const { t } = useTranslation('common');
  const {
    products,
    paginatorInfo,
    isLoading,
    loadMore,
    hasNextPage,
    isLoadingMore,
  } = useProducts(
    { tags: tag.slug },
    {
      staleTime: Infinity,
    }
  );
  return (
    <>
      <div className="flex flex-col items-center justify-between gap-1.5 px-4 pt-5 xs:flex-row md:px-6 md:pt-6 lg:px-7 3xl:px-8">
        <h2 className="font-medium capitalize text-dark-100 dark:text-light">
          #{tag.name}
        </h2>
        <div>
          {t('text-total')} {paginatorInfo?.total} {t('text-product-found')}
        </div>
      </div>
      <Grid
        products={products}
        onLoadMore={loadMore}
        hasNextPage={hasNextPage}
        isLoadingMore={isLoadingMore}
        isLoading={isLoading}
      />
    </>
  );
};

TagPage.getLayout = function getLayout(page) {
  return <Layout>{page}</Layout>;
};
export default TagPage;
