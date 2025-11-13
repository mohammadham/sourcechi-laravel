import invariant from 'tiny-invariant';
import client from '@/data/client';
import type {
  GetStaticPaths,
  GetStaticProps,
  InferGetStaticPropsType,
} from 'next';
import { Product } from '@/types';
import { serverSideTranslations } from 'next-i18next/serverSideTranslations';

// This function gets called at build time
type ParsedQueryParams = {
  productSlug: string;
};

export const getStaticPaths: GetStaticPaths<ParsedQueryParams> = async ({
  locales,
}) => {
  invariant(locales, 'locales is not defined');
  
  try {
    // Fetch all products to check their language availability
    const { data } = await client.products.all({ limit: 500 });
    
    const paths: Array<{ params: { productSlug: string }; locale: string }> = [];

    // For each product, determine which locales it should be available in
    data?.forEach((product: any) => {
      // If product has all_languages = true, add it to all locales
      if (product.all_languages === true) {
        locales?.forEach((locale) => {
          paths.push({
            params: { productSlug: product.slug },
            locale,
          });
        });
      }
      // If product has available_languages array, add it only to those locales
      else if (product.available_languages && Array.isArray(product.available_languages)) {
        product.available_languages.forEach((lang: string) => {
          // Only add if this language is in the supported locales
          if (locales?.includes(lang)) {
            paths.push({
              params: { productSlug: product.slug },
              locale: lang,
            });
          }
        });
      }
      // Fallback: If neither all_languages nor available_languages is set,
      // use the product's language field (backward compatibility)
      else if (product.language) {
        if (locales?.includes(product.language)) {
          paths.push({
            params: { productSlug: product.slug },
            locale: product.language,
          });
        }
      }
    });

    console.log(`[getStaticPaths] Generated ${paths.length} paths for ${data?.length || 0} products`);

    return {
      paths: paths || [],
      fallback: 'blocking', // Enable ISR for new products
    };
  } catch (error) {
    console.warn('Failed to fetch products during build:', error);
    return {
      paths: [],
      fallback: 'blocking',
    };
  }
};

type PageProps = {
  product: Product;
};

export const getStaticProps: GetStaticProps<
  PageProps,
  ParsedQueryParams
> = async ({ params, locale }) => {
  const { productSlug } = params!; //* we know it's required because of getStaticPaths
  try {
    const product = await client.products.get({
      slug: productSlug,
      language: locale,
    });
    return {
      props: {
        product,
        ...(await serverSideTranslations(locale!, ['common'])),
      },
      revalidate: 60, // In seconds
    };
  } catch (error) {
    //* if we get here, the product doesn't exist or something else went wrong
    return {
      notFound: true,
    };
  }
};
